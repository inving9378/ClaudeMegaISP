<?php

namespace App\Services\Metricas;

/**
 * Una conexión abierta con un router durante una corrida del colector (item #988).
 * Solo lectura: nada aquí escribe ni renombra en el router.
 */
interface SesionRouter
{
    /** Host:puerto con el que de verdad se conectó (después de cualquier redirección). */
    public function host(): string;

    public function usuario(): string;

    /** false = se está usando el usuario de respaldo (el de la API de siempre). */
    public function soloLectura(): bool;

    /**
     * true = el host y las credenciales no son los del router registrado sino los de
     * MIKROTIK_DEV_* (redirección de RouterConnection fuera de production).
     */
    public function redirigido(): bool;

    /** El /system identity que contesta el router. */
    public function identidad(): string;

    /**
     * Contadores de las interfaces pedidas, en UNA lectura cuando todas traen nombre.
     * Cada pedida trae su nombre tal cual (con espacios) y/o su VLAN ID; si el nombre no está
     * o no aparece, se busca por VLAN ID.
     *
     * @param  array<string, array{interfaz: ?string, vlan_id: ?int}>  $pedidas  clave => pedida
     * @return array<string, LecturaInterfaz|null>  null = no existe (o el VLAN ID es ambiguo)
     */
    public function interfaces(array $pedidas): array;

    /** Ping desde el router a una IP: pocos paquetes con intervalo corto. */
    public function ping(string $ip, int $paquetes, string $intervalo): LecturaPing;

    /**
     * SOLO INFORMATIVO (dry-run): tasas en vivo de las interfaces VLAN con esos VLAN ID, como las
     * muestra Winbox (rx/tx desde el router), vía /interface/monitor-traffic once.
     *
     * @param  int[]  $vlanIds
     * @return array<int, array{vlan_id:int, nombre:string, padre:?string, rx_bps:?int, tx_bps:?int, rx_pps:?int, tx_pps:?int}>
     */
    public function tasasEnVivo(array $vlanIds): array;

    public function cerrar(): void;
}
