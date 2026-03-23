<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AppAccessGrantedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $plainPassword
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $fullName = trim(($notifiable->fullname ?? '') ?: ($notifiable->name ?? 'Usuario'));

        return (new MailMessage)
            ->subject('Tus accesos a ENARM CCM ya están habilitados')
            ->greeting("Hola {$fullName}")
            ->line('Tu acceso a la app ENARM CCM ha sido habilitado.')
            ->line('Ya puedes iniciar sesión con los siguientes datos:')
            ->line("**Usuario:** {$notifiable->email}")
            ->line("**Contraseña:** {$this->plainPassword}")
            ->line('Por seguridad, te recomendamos cambiar tu contraseña después de iniciar sesión.')
            ->salutation('ENARM CCM');
    }
}