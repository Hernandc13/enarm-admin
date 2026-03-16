<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Mail\WelcomeAccessMail;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
     * Al guardar cambios, redirige a la lista
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
     * Header actions: Reenviar accesos
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
                ->modalDescription('Puedes generar una contraseña automática o capturar una manual. Esa será la que se enviará al correo.')
                ->form([
                    Forms\Components\Radio::make('generate_password')
                        ->label('Tipo de contraseña')
                        ->options([
                            1 => 'Generar contraseña automáticamente',
                            0 => 'Capturar contraseña manualmente',
                        ])
                        ->default(1)
                        ->live()
                        ->required(),

                    Forms\Components\TextInput::make('password_plain')
                        ->label('Contraseña')
                        ->password()
                        ->revealable()
                        ->minLength(8)
                        ->visible(fn (Forms\Get $get) => (string) $get('generate_password') === '0')
                        ->required(fn (Forms\Get $get) => (string) $get('generate_password') === '0'),

                    Forms\Components\TextInput::make('password_plain_confirmation')
                        ->label('Confirmar contraseña')
                        ->password()
                        ->revealable()
                        ->minLength(8)
                        ->visible(fn (Forms\Get $get) => (string) $get('generate_password') === '0')
                        ->required(fn (Forms\Get $get) => (string) $get('generate_password') === '0'),
                ])
                ->action(function (array $data): void {
                    try {
                        $generate = filter_var($data['generate_password'] ?? true, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                        $generate = $generate ?? ((string) ($data['generate_password'] ?? '1') !== '0');

                        if ($generate) {
                            $plain = Str::password(12);
                        } else {
                            $plain = trim((string) ($data['password_plain'] ?? ''));
                            $confirm = trim((string) ($data['password_plain_confirmation'] ?? ''));

                            if ($plain === '') {
                                throw ValidationException::withMessages([
                                    'password_plain' => 'Debes capturar una contraseña.',
                                ]);
                            }

                            if (mb_strlen($plain) < 8) {
                                throw ValidationException::withMessages([
                                    'password_plain' => 'La contraseña debe tener al menos 8 caracteres.',
                                ]);
                            }

                            if ($plain !== $confirm) {
                                throw ValidationException::withMessages([
                                    'password_plain_confirmation' => 'La confirmación de la contraseña no coincide.',
                                ]);
                            }
                        }

                       // $this->record->password = Hash::make($plain);
                        $this->record->password = $plain;
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