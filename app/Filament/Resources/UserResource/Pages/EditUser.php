<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Mail\WelcomeAccessMail;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function authorizeAccess(): void
    {
        parent::authorizeAccess();

        // Bloqueo total para usuarios Moodle
        if ((bool) ($this->record->is_from_moodle ?? false)) {
            abort(403, 'Los usuarios sincronizados desde Moodle no se editan aquí.');
        }
    }

    /**
     * ✅ Al guardar cambios, redirige a la lista
     */
    protected function getRedirectUrl(): string
    {
        return static::$resource::getUrl('index');
    }

    /**
     * ✅ Solo Guardar / Cancelar (sin borrar)
     */
    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
            $this->getCancelFormAction(),
        ];
    }

    /**
     * ✅ Acciones header: Reenviar accesos (sin Delete)
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('resendAccess')
                ->label('Reenviar accesos')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->visible(fn () => ! (bool) ($this->record->is_from_moodle ?? false))
                ->modalHeading('Reenviar accesos al usuario')
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

                        $plain = $generate
                            ? Str::password(12)
                            : trim((string) ($data['password_plain'] ?? ''));

                        if ($plain === '' || mb_strlen($plain) < 8) {
                            $plain = Str::password(12);
                        }

                        $this->record->password = Hash::make($plain);
                        $this->record->save();

                        Mail::to($this->record->email)->send(new WelcomeAccessMail([
                            'name'      => $this->record->name,
                            'last_name' => $this->record->last_name ?? '',
                            'email'     => $this->record->email,
                            'password'  => $plain,
                            'app_url'   => config('app.url'),
                        ]));

                        Notification::make()
                            ->title('Accesos enviados correctamente')
                            ->success()
                            ->send();

                        // ✅ Redirigir al listado después de reenviar accesos
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
