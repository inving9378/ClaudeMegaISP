<?php

namespace App\Services\OltDriver\Capabilities;

/**
 * Driver puede ejecutar diagnósticos avanzados sobre una ONU:
 * estado completo, configuración activa, IPs y búsqueda por número de serie.
 */
interface SupportsAdvancedDiagnostics
{
    /**
     * Estado completo de la ONU (señal, puertos, VLANs activas, etc).
     *
     * Shape: ['success'=>bool, 'response'=>array]
     */
    public function getOnuFullStatus(string $onuId): array;

    /**
     * Configuración actual corriendo en la ONU.
     *
     * Shape: ['success'=>bool, 'response'=>array]
     */
    public function getOnuRunningConfig(string $onuId): array;

    /**
     * IP de gestión asignada a la ONU.
     *
     * Shape: ['success'=>bool, 'response'=>array{ip_address:string|null, ...}]
     */
    public function getOnuMgmtIp(string $onuId): array;

    /**
     * IP del cliente asignada por la ONU (WAN/DHCP).
     *
     * Shape: ['success'=>bool, 'response'=>array{ip_address:string|null, ...}]
     */
    public function getOnuIpAddress(string $onuId): array;

    /**
     * Detalle completo de una ONU buscada por número de serie físico.
     *
     * Shape: igual que OltDriverInterface::getOnuDetails — ['success'=>bool, 'onu_details'=>array]
     */
    public function getOnuDetailsBySn(string $sn): array;
}
