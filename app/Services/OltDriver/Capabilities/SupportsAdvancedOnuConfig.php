<?php

namespace App\Services\OltDriver\Capabilities;

/**
 * Driver puede aplicar configuraciones avanzadas de red en la ONU:
 * modo WAN, modo ONU (bridging/routing), IP de gestión, tipo de hardware,
 * canal PON, contraseña web, resync de config y reubicación de puerto.
 */
interface SupportsAdvancedOnuConfig
{
    /**
     * Establece el modo WAN de la ONU (PPPoE, DHCP, static, etc).
     *
     * @param string $mode  ej. 'pppoe'|'dhcp'|'static'|'setup_via_onu_webpage'
     *
     * Shape: ['success'=>bool, 'message'=>string|null]
     */
    public function setOnuWanMode(string $onuId, string $mode, array $data): array;

    /**
     * Actualiza el modo de operación de la ONU (bridging/routing) y VLAN principal.
     * Puede ser una operación compuesta (VLAN + modo + WAN mode).
     *
     * Shape: ['success'=>bool, 'vlan'=>array, 'onuMode'=>array, 'wanMode'=>array|null]
     */
    public function updateOnuMode(string $onuId, array $data): array;

    /**
     * Cambia el tipo/perfil de hardware de la ONU (template de configuración).
     *
     * Shape: ['success'=>bool, 'type'=>array, 'profile'=>array]
     */
    public function changeOnuType(string $onuId, array $data): array;

    /**
     * Mueve la ONU a un canal PON diferente dentro de la misma OLT.
     *
     * Shape: ['success'=>bool, 'message'=>string|null]
     */
    public function updateOnuPonChannel(string $onuId, array $data): array;

    /**
     * Cambia la contraseña de administración web de la ONU.
     *
     * Shape: ['success'=>bool, 'message'=>string|null]
     */
    public function changeOnuWebPassword(string $onuId): array;

    /**
     * Fuerza la re-sincronización de la configuración desde la plataforma hacia la ONU.
     *
     * Shape: ['success'=>bool, 'message'=>string|null]
     */
    public function resyncOnuConfig(string $onuId): array;

    /**
     * Mueve una ONU a otro slot/puerto de la OLT (cambio de posición física).
     *
     * Shape: ['success'=>bool, 'message'=>string|null]
     */
    public function moveOnu(string $onuId, array $data): array;

    /**
     * Actualiza la configuración del service port de la ONU.
     *
     * Shape: ['success'=>bool, 'message'=>string|null]
     */
    public function updateOnuServicePort(string $onuId, array $data): array;

    /**
     * Configura la IP de gestión de la ONU (TR-069 / OMCI management).
     *
     * @param string $type  'inactive'|'dhcp'|'static'
     *
     * Shape: ['success'=>bool, 'message'=>string|null]
     */
    public function setOnuMgmtIp(string $onuId, array $data, string $type = 'inactive'): array;
}
