<?php

namespace App\Services\OltDriver\Capabilities;

/**
 * Driver puede gestionar VLANs: asignación a ONUs y alta en OLT.
 */
interface SupportsVlanManagement
{
    /**
     * Cambia las VLANs asociadas a una ONU (service port VLAN/SVLAN).
     *
     * Shape: ['success'=>bool, 'message'=>string|null]
     */
    public function setOnuVlans(string $onuId, array $data): array;

    /**
     * Agrega una nueva VLAN a la configuración de una OLT.
     *
     * Shape: ['success'=>bool, 'message'=>string|null]
     */
    public function addVlan(string $oltId, array $data): array;
}
