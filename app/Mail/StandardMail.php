<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StandardMail extends Mailable
{
    use Queueable, SerializesModels;

    public $emailProps;

    public function __construct($emailProps)
    {
        $this->emailProps = $emailProps;
    }

    public function build()
    {
        $mail = $this->from(config('mail.from.address'), config('mail.from.name'))
            ->subject($this->emailProps['title'] ?? 'Notificación')
            ->view('emails.custom', ['htmlContent' => $this->emailProps['body']]);

        if (!empty($this->emailProps['to'])) {
            $toRecipients = is_array($this->emailProps['to']) ? $this->emailProps['to'] : [$this->emailProps['to']];
            $mail->to($toRecipients);
        }

        if (!empty($this->emailProps['cc'])) {
            $ccRecipients = is_array($this->emailProps['cc'])
                ? $this->emailProps['cc']
                : array_map('trim', explode(',', $this->emailProps['cc']));
            $mail->cc($ccRecipients);
        }

        // Adjuntos opcionales (item #480: la Prefactura en PDF del botón «Enviar»). Viajan en
        // base64 porque StandardNotification va por cola y el payload del job es JSON.
        foreach ($this->emailProps['attachments'] ?? [] as $adjunto) {
            if (empty($adjunto['data']) || empty($adjunto['name'])) {
                continue;
            }
            $mail->attachData(base64_decode($adjunto['data']), $adjunto['name'], [
                'mime' => $adjunto['mime'] ?? 'application/octet-stream',
            ]);
        }

        return $mail;
    }
}
