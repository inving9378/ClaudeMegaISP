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

        return $mail;
    }
}
