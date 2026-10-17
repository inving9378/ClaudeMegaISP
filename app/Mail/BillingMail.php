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

    /**
     * @param array|null $documento lo que decidió DocumentoNotificacionResolver al enviar
     *        (asunto, titulo, mensaje, etiqueta, folios, adjuntos[data,name,mime]); null = como
     *        siempre: el asunto guardado y el PDF de pdf_path (FAC-04, item #509).
     */
    public function __construct(
        private readonly BillingNotification $notification,
        private readonly ?array $documento = null,
    ) {}

    public function build(): static
    {
        $subject = $this->documento['asunto']
            ?? $this->notification->subject
            ?? $this->defaultSubject();

        $mail = $this->from(config('mail.from.address'), config('mail.from.name'))
            ->subject($subject)
            ->view('emails.billing', ['notification' => $this->notification, 'documento' => $this->documento]);

        if (! empty($this->documento['adjuntos'])) {
            foreach ($this->documento['adjuntos'] as $adjunto) {
                $mail->attachData($adjunto['data'], $adjunto['name'], ['mime' => $adjunto['mime'] ?? 'application/pdf']);
            }

            return $mail;
        }

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
