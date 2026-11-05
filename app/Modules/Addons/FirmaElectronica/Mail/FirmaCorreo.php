<?php

namespace App\Modules\Addons\FirmaElectronica\Mail;

use Illuminate\Mail\Mailable;

/** Correo al firmante (invitación o código OTP). Texto simple; el envío lo gobierna firma_electronica.correo_activo. */
class FirmaCorreo extends Mailable
{
    public function __construct(private string $asunto, private string $cuerpoHtml) {}

    public function build(): static
    {
        return $this->subject($this->asunto)->html($this->cuerpoHtml);
    }
}
