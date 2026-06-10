<?php

namespace App\Services\OltDriver\Capabilities;

/**
 * Driver puede controlar el servicio CATV (overlay de RF sobre GPON).
 * No todas las ONUs ni todas las OLTs soportan CATV — presente principalmente
 * en despliegues triple-play donde el operador entrega TV por la fibra.
 */
interface SupportsCatv
{
    /**
     * Habilita o deshabilita el overlay CATV en la ONU.
     *
     * @param string $catv  'enable'|'disable'
     *
     * Shape: ['success'=>bool, 'message'=>string|null]
     */
    public function setOnuCatv(string $onuId, string $catv = 'enable'): array;
}
