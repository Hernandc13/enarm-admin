<?php

namespace App\Mail;

use App\Services\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MoodleAccessMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param array{name:string,email:string,app_url?:string,logo_url?:string,moodle_url?:string} $payload */
    public function __construct(private array $payload) {}

    public function build()
    {
        $service = app(EmailTemplateService::class);

        $out = $service->render('moodle', [
            'name'      => (string) ($this->payload['name'] ?? 'Alumno'),
            'email'     => (string) ($this->payload['email'] ?? ''),
            'password'  => (string) ($this->payload['password'] ?? ''), // opcional
            'app_url'   => (string) ($this->payload['app_url'] ?? config('app.url')),
            'logo_url'  => (string) ($this->payload['logo_url'] ?? ''),
            'moodle_url'=> (string) ($this->payload['moodle_url'] ?? config('services.moodle.url')),
        ]);

        return $this->subject($out['subject'])->html($out['html']);
    }
}
