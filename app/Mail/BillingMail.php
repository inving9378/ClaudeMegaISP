<?php

namespace App\Mail;

use App\Models\BillingNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class BillingMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private readonly BillingNotification $notification
    ) {}

    public function build(): static
    {
        $subject = $this->notification->subject
            ?? $this->defaultSubject();

        $mail = $this->from(config('mail.from.address'), config('mail.from.name'))
            ->subject($subject)
            ->view('emails.billing', ['notification' => $this->notification]);

        $disk = config('billing.pdf_disk', 'local');
        $path = $this->notification->pdf_path;

        if ($path && Storage::disk($disk)->exists($path)) {
            $mail->attachData(
                Storage::disk($disk)->get($path),
                basename($path),
                ['mime' => 'application/pdf']
            );
        }

        return $mail;
    }

    private function defaultSubject(): string
    {
        return match ($this->notification->document_type) {
            'prefactura' => 'Tu aviso de cobro — ' . config('mail.from.name'),
            'recibo'     => 'Recibo de pago confirmado — ' . config('mail.from.name'),
            default      => 'Documento de facturación — ' . config('mail.from.name'),
        };
    }
}
