<?php

namespace App\Services;

use App\Models\Client;
use App\Models\EmailSetting;
use App\Notifications\StandardNotification;
use Illuminate\Support\Facades\Notification;

class EmailConfigService
{
    public static function setMailConfig()
    {
        // Obtener la configuración de correo desde la base de datos
        $emailSetting = EmailSetting::first();

       // if ($emailSetting && config('app.env') === "production") {
            // Establecer la configuración dinámica
            config([
                'mail.mailers.smtp.transport' => $emailSetting->mail_mailer,
                'mail.mailers.smtp.host' => $emailSetting->mail_host,
                'mail.mailers.smtp.port' => $emailSetting->mail_port,
                'mail.mailers.smtp.username' => $emailSetting->mail_username,
                'mail.mailers.smtp.password' => $emailSetting->mail_password,
                'mail.mailers.smtp.encryption' => $emailSetting->mail_encryption,
                'mail.from.address' => $emailSetting->mail_from_address,
                'mail.from.name' => $emailSetting->mail_from_name,
            ]);
        //}
    }


    public function sendEmail(string $type, $email)
    {
        $client = Client::find($email->client_id);
        $emailProps = $this->getEmailProps($email);
        Notification::route('mail', $emailProps['to'])
            ->notify(new StandardNotification($client, ['mail'], $emailProps));
    }


    public function getEmailProps($email): array
    {
        $props = [
            'subject' => $email->subject,
            'body' => $email->html,
            'to' => $email->recipient_email,
        ];
        if ($email->cc_email) {
            $props['cc'] = $email->cc_email;
        }
        return $props;
    }
}
