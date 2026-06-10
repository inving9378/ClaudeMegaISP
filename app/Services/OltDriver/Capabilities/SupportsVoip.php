<?php

namespace App\Services\OltDriver\Capabilities;

/**
 * Driver puede configurar servicios de VoIP en la ONU.
 */
interface SupportsVoip
{
    /**
     * Habilita o deshabilita el puerto VoIP de la ONU.
     *
     * @param string $status  'enable'|'disable'
     *
     * Shape: ['success'=>bool, 'message'=>string|null]
     */
    public function setOnuVoipPort(string $onuId, string $status, array $data): array;

    /**
     * Actualiza simultáneamente la IP de gestión (TR-069) y la configuración VoIP.
     * Operación compuesta que puede ejecutarse en paralelo internamente.
     *
     * Shape: ['success'=>bool, 'tr069'=>array, 'voIp'=>array]
     */
    public function updateOnuMgmtAndVoip(string $onuId, array $data): array;
}
