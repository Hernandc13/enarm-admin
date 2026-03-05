<?php

namespace App\Mail;

use App\Services\EmailTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdminWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param array{name:string,last_name?:string,email:string,password:string,app_url?:string,panel_url?:string,logo_url?:string} $payload */
    public function __construct(public array $payload) {}

    public function build()
    {
        $name = trim(($this->payload['name'] ?? '') . ' ' . ($this->payload['last_name'] ?? ''));

        $service = app(EmailTemplateService::class);

        $out = $service->render('admin', [
            'name'     => $name !== '' ? $name : 'Administrador',
            'email'    => (string) ($this->payload['email'] ?? ''),
            'password' => (string) ($this->payload['password'] ?? ''),
            'app_url'  => (string) ($this->payload['app_url'] ?? config('app.url')),
            'panel_url'=> (string) ($this->payload['panel_url'] ?? ''),
            'logo_url' => (string) ($this->payload['logo_url'] ?? ''),
        ]);

        return $this->subject($out['subject'])->html($out['html']);
    }
}
