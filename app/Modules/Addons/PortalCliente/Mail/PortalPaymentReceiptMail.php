<?php

namespace App\Modules\Addons\PortalCliente\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Recibo de pago enviado al cliente tras un cargo OpenPay exitoso en el portal.
 * Se despacha vía cola (ShouldQueue) para no bloquear el flujo de pago.
 */
class PortalPaymentReceiptMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public array $data)
    {
    }

    public function build(): static
    {
        return $this->from(config('mail.from.address'), config('mail.from.name'))
            ->subject('Recibo de pago — Factura #' . $this->data['invoice_number'])
            ->view('addon-portal-cliente::emails.payment_receipt', ['d' => $this->data]);
    }
}
