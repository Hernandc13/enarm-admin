<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Mail\WelcomeAccessMail;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    private ?string $plainPasswordForMail = null;
    private bool $sendWelcomeForMail = true;

    protected function getRedirectUrl(): string
    {
        return static::$resource::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $auto = (string) ($data['auto_password'] ?? '1') !== '0';
        $send = (bool) ($data['send_welcome'] ?? true);

        $plain = trim((string) ($data['password_plain'] ?? ''));
        $plainConfirmation = trim((string) ($data['password_plain_confirmation'] ?? ''));

        if ($auto) {
            $plain = Str::password(12);
        } else {
            if ($plain === '') {
                throw ValidationException::withMessages([
                    'password_plain' => 'Debes capturar una contraseña manual.',
                ]);
            }

            if (mb_strlen($plain) < 8) {
                throw ValidationException::withMessages([
                    'password_plain' => 'La contraseña debe tener al menos 8 caracteres.',
                ]);
            }

            if ($plain !== $plainConfirmation) {
                throw ValidationException::withMessages([
                    'password_plain_confirmation' => 'La confirmación de la contraseña no coincide.',
                ]);
            }
        }

        $this->plainPasswordForMail = $plain;
        $this->sendWelcomeForMail = $send;

        unset(
            $data['auto_password'],
            $data['send_welcome'],
            $data['password_plain'],
            $data['password_plain_confirmation']
        );

        // Tu modelo User ya tiene cast: 'password' => 'hashed'
        $data['password'] = $plain;

        // Usuarios APP, no admins
        $data['is_admin'] = false;

        // Acceso a la app habilitado al crear
        $data['has_app_access'] = true;
        $data['granted_at'] = now();
        $data['revoked_at'] = null;

        // Usuario manual/excel, no Moodle
        $data['is_from_moodle'] = false;
        $data['moodle_user_id'] = null;
        $data['synced_at'] = null;

        return $data;
    }

    protected function afterCreate(): void
    {
        if (! $this->sendWelcomeForMail || ! $this->plainPasswordForMail) {
            Notification::make()
                ->title('Usuario creado')
                ->body('El usuario se creó correctamente y no se enviaron accesos por correo.')
                ->success()
                ->send();

            return;
        }

        $user = $this->record;

        try {
            Mail::to($user->email)->send(new WelcomeAccessMail([
                'name'      => $user->name,
                'last_name' => $user->last_name ?? '',
                'email'     => $user->email,
                'password'  => $this->plainPasswordForMail,
                'app_url'   => config('app.url'),
            ]));

            Notification::make()
                ->title('Usuario creado y correo enviado')
                ->body('Los accesos fueron enviados correctamente al correo del usuario.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Usuario creado, pero no se pudo enviar el correo')
                ->body($e->getMessage())
                ->warning()
                ->persistent()
                ->send();
        }
    }
}