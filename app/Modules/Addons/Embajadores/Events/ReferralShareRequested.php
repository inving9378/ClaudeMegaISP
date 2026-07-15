<?php

namespace App\Modules\Addons\Embajadores\Events;

/**
 * Evento de dominio: un embajador pidió compartir su código de referido con una
 * lista de contactos por WhatsApp. Embajadores NO conoce el transporte (Evolution/
 * gateway) — solo publica la intención ya con el mensaje renderizado por contacto.
 * Item #253: reemplaza la llamada directa a Marketing\EvolutionApiService.
 */
class ReferralShareRequested
{
    /**
     * @param array<int, array{name:string, phone:string, body:string}> $contacts
     */
    public function __construct(
        public readonly int $embajadorId,
        public readonly string $referralCode,
        public readonly array $contacts,
    ) {
    }
}
