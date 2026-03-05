<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Mail\WelcomeAccessMail;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    private ?string $plainPasswordForMail = null;
    private bool $sendWelcomeForMail = true;

    /**
     *  Al terminar de crear, redirige a la lista de usuarios
     */
    protected function getRedirectUrl(): string
    {
        return static::$resource::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $auto  = (bool) ($data['auto_password'] ?? true);
        $send  = (bool) ($data['send_welcome'] ?? true);
        $plain = trim((string) ($data['password_plain'] ?? ''));

        if ($auto) {
            $plain = Str::password(12);
        } else {
            if ($plain === '' || mb_strlen($plain) < 8) {
                $plain = Str::password(12);
            }
        }

        $this->plainPasswordForMail = $plain;
        $this->sendWelcomeForMail = $send;

        unset($data['auto_password'], $data['send_welcome'], $data['password_plain']);

        $data['password'] = Hash::make($plain);

        // usuarios de APP, NO admins
        $data['is_admin'] = false;

        // acceso automático
        $data['has_app_access'] = true;
        $data['granted_at'] = now();
        $data['revoked_at'] = null;

        // manual/excel => no moodle
        $data['is_from_moodle'] = false;
        $data['moodle_user_id'] = null;
        $data['synced_at'] = null;

        return $data;
    }

    protected function afterCreate(): void
    {
        if (! $this->sendWelcomeForMail || ! $this->plainPasswordForMail) {
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
