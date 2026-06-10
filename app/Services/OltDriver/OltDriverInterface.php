<?php

namespace App\Services\OltDriver;

/**
 * Contrato núcleo que todo driver OLT debe implementar.
 *
 * Cubre las operaciones mínimas necesarias para sincronización de inventario,
 * gestión del ciclo de vida de ONUs y control de acceso. Las capacidades
 * opcionales (diagnósticos avanzados, VoIP, puertos, CATV, etc.) se declaran
 * en App\Services\OltDriver\Capabilities\Supports*.
 *
 * Todas las implementaciones devuelven array con al mínimo ['success' => bool].
 * En caso de error, incluyen 'message' => string describiendo la causa.
 */
interface OltDriverInterface
{
    /**
     * Nombre legible del driver, usado en logs y UI.
     * Ejemplo: 'SmartOLT', 'Huawei'.
     */
    public function getName(): string;

    // ── Lecturas globales ─────────────────────────────────────────────────

    /**
     * Lista de OLTs gestionadas por esta plataforma.
     *
     * Shape: ['success'=>bool, 'data'=>array<array{id:int|string, name:string, ...}>]
     */
    public function listOlts(): array;

    /**
     * Catálogo de perfiles de velocidad disponibles.
     *
     * Shape: ['success'=>bool, 'response'=>array<array{id:int|string, name:string, speed:string, direction:string, type:string}>]
     */
    public function listSpeedProfiles(): array;

    // ── Lecturas de ONUs (bulk — para crons de sincronización) ────────────

    /**
     * ONUs detectadas pero aún no configuradas en la OLT dada (o en todas si $oltId es null).
     *
     * Shape: ['success'=>bool, 'response'=>array<array{sn:string, olt_id:int|string, ...}>]
     */
    public function getUnconfiguredOnus(?string $oltId = null): array;

    /**
     * Lista completa de ONUs de una OLT con todos sus campos de inventario.
     *
     * Shape: ['success'=>bool, 'onus'=>array<array{sn:string, unique_external_id:string, olt_id:int|string,
     *         board:string|null, port:string|null, onu:int, status:string, signal:string,
     *         signal_1310:string, signal_1490:string, ...}>]
     */
    public function getOnusByOlt(string $oltId): array;

    /**
     * Lecturas de señal óptica de todas las ONUs, opcionalmente filtrado por OLT.
     *
     * Shape: ['success'=>bool, 'response'=>array<array{sn:string, olt_id:int|string,
     *         unique_external_id:string, board:string|null, port:string|null, onu:int,
     *         zone_id:int|null, signal:string, signal_1310:string, signal_1490:string}>]
     */
    public function getOnusSignals(?string $oltId = null): array;

    /**
     * Estado operativo (Online/Offline/LOS/…) de todas las ONUs, opcionalmente filtrado por OLT.
     *
     * Shape: ['success'=>bool, 'response'=>array<array{sn:string, olt_id:int|string,
     *         unique_external_id:string, board:string|null, port:string|null, onu:int,
     *         zone_id:int|null, status:string}>]
     */
    public function getOnusStatus(?string $oltId = null): array;

    // ── Lecturas de ONU (individual — para UI interactiva) ────────────────

    /**
     * Detalle completo de una ONU por su identificador externo.
     *
     * Shape: ['success'=>bool, 'onu_details'=>array{sn:string, unique_external_id:string,
     *         olt_id:int|string, olt_name:string, board:string|null, port:string|null,
     *         onu:int, status:string, signal:string, signal_1310:string, signal_1490:string,
     *         mode:string, wan_mode:string, vlan:string|null, service_ports:array|null,
     *         ethernet_ports:array|null, wifi_ports:array|null, voip_ports:array|null, ...}]
     */
    public function getOnuDetails(string $onuId): array;

    /**
     * Señal óptica actual de una ONU.
     *
     * Shape: ['success'=>bool, 'onu_signal'=>string, 'onu_signal_1310'=>string, 'onu_signal_1490'=>string]
     * Nota: 'onu_signal' es descriptivo ('Very good'|'Warning'|'Critical'|'-').
     *       'onu_signal_1310'/'onu_signal_1490' son cadenas numéricas en dBm o '-'.
     */
    public function getOnuSignal(string $onuId): array;

    /**
     * Estado operativo actual de una ONU.
     *
     * Shape: ['success'=>bool, 'onu_status'=>string, 'last_status_change'=>string|null]
     * Nota: 'onu_status' = 'Online'|'Offline'|'LOS'|'Power fail'|…
     */
    public function getOnuStatus(string $onuId): array;

    // ── Ciclo de vida de ONU ──────────────────────────────────────────────

    /**
     * Autoriza/provisiona una ONU en la OLT.
     *
     * Shape: ['success'=>bool, 'message'=>string|null]
     */
    public function authorizeOnu(array $data): array;

    /**
     * Elimina/desautoriza una ONU de la OLT.
     *
     * Shape: ['success'=>bool, 'message'=>string|null]
     */
    public function deauthorizeOnu(string $onuId): array;

    /**
     * Habilita o deshabilita el servicio de una ONU (bloqueo/desbloqueo).
     *
     * Shape: ['success'=>bool, 'message'=>string|null]
     */
    public function setOnuEnabled(string $onuId, bool $enabled): array;

    /**
     * Reinicia una ONU remotamente.
     *
     * Shape: ['success'=>bool, 'message'=>string|null]
     */
    public function rebootOnu(string $onuId): array;

    /**
     * Aplica un perfil de velocidad (QoS/ancho de banda) a una ONU.
     *
     * Shape: ['success'=>bool, 'message'=>string|null]
     */
    public function setOnuSpeedProfile(string $onuId, array $data): array;
}
