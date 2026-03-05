<?php

namespace App\Filament\Resources\AdminResource\Pages;

use App\Filament\Resources\AdminResource;
use App\Mail\AdminWelcomeMail;
use App\Models\Setting;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class EditAdmin extends EditRecord
{
    protected static string $resource = AdminResource::class;

    /**
     * Redirige al listado (Index) después de guardar
     */
    protected function getRedirectUrl(): string
    {
        return static::$resource::getUrl('index');
    }

    /**
     * Solo Guardar / Cancelar
     */
    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
            $this->getCancelFormAction(),
        ];
    }

    /**
     *  Quita el botón de borrar
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('resendAccess')
                ->label('Reenviar accesos')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->modalHeading('Reenviar accesos al administrador')
                ->modalDescription('Puedes definir una contraseña o dejarla vacía para generarla automáticamente.')
                ->form([
                    Forms\Components\Toggle::make('generate_password')
                        ->label('Generar contraseña automáticamente')
                        ->default(true)
                        ->live(),

                    Forms\Components\TextInput::make('password_plain')
                        ->label('Contraseña')
                        ->password()
                        ->revealable()
                        ->minLength(8)
                        ->visible(fn (Forms\Get $get) => ! $get('generate_password'))
                        ->required(fn (Forms\Get $get) => ! $get('generate_password')),
                ])
                ->action(function (array $data): void {
                    try {
                        $generate = (bool) ($data['generate_password'] ?? true);

                        if ($generate) {
                            $plain = Str::password(12);
                        } else {
                            $plain = trim((string) ($data['password_plain'] ?? ''));
                            if ($plain === '' || mb_strlen($plain) < 8) {
                                $plain = Str::password(12);
                            }
                        }

                        // 1) Guardar password nueva
                        $this->record->password = Hash::make($plain);
                        $this->record->save();

                        // 2) Preparar payload ADMIN
                        $appUrl   = (string) config('app.url');
                        $panelUrl = rtrim($appUrl, '/') . '/admin_enarm'; // <- ajusta si tu panel cambia
                        $logoUrl  = (string) Setting::get('email_logo_url', 'https://i.imgur.com/WHdjsG4.png');

                        $payload = [
                            'name'      => (string) ($this->record->name ?? ''),
                            'last_name' => (string) ($this->record->last_name ?? ''),
                            'email'     => (string) ($this->record->email ?? ''),
                            'password'  => $plain,
                            'app_url'   => $appUrl,
                            'panel_url' => $panelUrl,
                            'logo_url'  => $logoUrl,
                        ];

                        Mail::to($this->record->email)->send(new AdminWelcomeMail($payload));

                        Notification::make()
                            ->title('Accesos enviados correctamente')
                            ->success()
                            ->send();

                        // Redirigir al index
                        $this->redirect(static::$resource::getUrl('index'));

                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('No se pudo enviar el correo')
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),
        ];
    }
}
