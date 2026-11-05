<?php

namespace App\Modules\Addons\Flotas\Mail;

use Illuminate\Mail\Mailable;

/** Aviso de una regla de alerta del cliente (portal Flotas). */
class FleetClientRuleAlertMail extends Mailable
{
    public function __construct(public readonly string $titulo, public readonly string $mensaje) {}

    public function build(): static
    {
        return $this->subject('⚠️ ' . $this->titulo)
            ->html('<p>' . e($this->mensaje) . '</p><p>Puedes revisarlo en tu portal de Flotas.</p>');
    }
}
