<?php

namespace App\Services\OltDriver\Capabilities;

/**
 * Driver puede leer la topología física de las OLTs: tarjetas, puertos PON,
 * puertos uplink, VLANs y estadísticas ambientales (temperatura/uptime).
 */
interface SupportsOltTopology
{
    /**
     * Shape: ['success'=>bool, 'response'=>array<array{slot:int|string, type:string, ...}>]
     */
    public function getOltCards(string $oltId): array;

    /**
     * Shape: ['success'=>bool, 'response'=>array<array{board:int, pon_port:int, onus:int, ...}>]
     */
    public function getOltPonPorts(string $oltId): array;

    /**
     * Shape: ['success'=>bool, 'response'=>array<array{name:string, ...}>]
     */
    public function getOltUplinkPorts(string $oltId): array;

    /**
     * PONs con interrupción activa. Shape: ['success'=>bool, 'response'=>array]
     */
    public function getOltOutagePons(string $oltId): array;

    /**
     * Shape: ['success'=>bool, 'response'=>array<array{id:int|string, description:string, ...}>]
     */
    public function getOltVlans(string $oltId): array;

    /**
     * Uptime y temperatura de todas las OLTs.
     *
     * Shape: ['success'=>bool, 'response'=>array<array{olt_id:int|string, uptime:string,
     *         temperature:float|null, ...}>]
     */
    public function getOltEnvironmentStats(): array;
}
