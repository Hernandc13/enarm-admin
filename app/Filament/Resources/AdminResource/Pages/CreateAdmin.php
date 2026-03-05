<?php

namespace App\Filament\Resources\AdminResource\Pages;

use App\Filament\Resources\AdminResource;
use App\Mail\AdminWelcomeMail;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CreateAdmin extends CreateRecord
{
    protected static string $resource = AdminResource::class;

    private ?string $plainPasswordForMail = null;
    private bool $sendWelcomeForMail = true;

    /**
     *  Redirige al listado (Index) después de crear
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
        $this->sendWelcomeForMail   = $send;

        unset($data['auto_password'], $data['send_welcome'], $data['password_plain']);

        $data['password'] = Hash::make($plain);
        $data['is_admin'] = true;

        return $data;
    }

    protected function afterCreate(): void
    {
        if (! $this->sendWelcomeForMail || ! $this->plainPasswordForMail) {
            return;
        }

        $user = $this->record;

        try {
            Mail::to($user->email)->send(new AdminWelcomeMail([
                'name'      => $user->name,
                'last_name' => $user->last_name ?? '',
                'email'     => $user->email,
                'password'  => $this->plainPasswordForMail,
                'app_url'   => config('app.url'),
                'panel_url' => config('app.url') . '/admin',
            ]));

            Notification::make()
                ->title('Administrador creado y correo enviado')
                ->success()
                ->send();

        } catch (\Throwable $e) {
            Notification::make()
                ->title('Administrador creado, pero no se pudo enviar el correo')
                ->body($e->getMessage())
                ->warning()
                ->persistent()
                ->send();
        }
    }
}
