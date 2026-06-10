<?php

namespace App\Services\OltDriver\Capabilities;

/**
 * Driver puede configurar y controlar los puertos LAN (ethernet y WiFi) de una ONU.
 */
interface SupportsOnuPorts
{
    /**
     * Configura un puerto ethernet de la ONU.
     *
     * @param string $type  'lan'|'wan'  (determina el endpoint a invocar)
     *
     * Shape: ['success'=>bool, 'message'=>string|null]
     */
    public function configureOnuEthernetPort(string $onuId, array $data, string $type = 'lan'): array;

    /**
     * Apaga/deshabilita un puerto ethernet de la ONU.
     *
     * Shape: ['success'=>bool, 'message'=>string|null]
     */
    public function shutdownOnuEthernetPort(string $onuId, array $data): array;

    /**
     * Configura un puerto WiFi de la ONU.
     *
     * @param string $type  'lan'|'wan'
     *
     * Shape: ['success'=>bool, 'message'=>string|null]
     */
    public function configureOnuWifiPort(string $onuId, array $data, string $type = 'lan'): array;

    /**
     * Apaga/deshabilita un puerto WiFi de la ONU.
     *
     * Shape: ['success'=>bool, 'message'=>string|null]
     */
    public function shutdownOnuWifiPort(string $onuId, array $data): array;
}
