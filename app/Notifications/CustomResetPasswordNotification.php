<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $token
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $resetUrl = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->email,
        ], false));

        $fullName = trim(($notifiable->fullname ?? '') ?: ($notifiable->name ?? 'Usuario'));

        $logoUrl = rtrim(config('app.url'), '/') . '/images/logo-enarm.png';

        return (new MailMessage)
            ->subject('Restablece tu contraseña - ENARM CCM')
            ->view('emails.reset-password-enarm', [
                'name' => $fullName,
                'resetUrl' => $resetUrl,
                'logoUrl' => $logoUrl,
                'expiresInMinutes' => 10,
            ]);
    }
}