<?php

namespace App\Modules\Addons\Flotas\Services\Gps;

/**
 * Contrato que todo driver de GPS debe implementar.
 *
 * El listener TCP (Fase 2.3) recibe bytes crudos del dispositivo, detecta el
 * fabricante con supports() y delega el parseo a parse(). Hoy solo existe
 * MockDriver; al llegar el hardware Ruptela/Concox solo se añade su driver
 * sin tocar el resto del sistema.
 */
interface GpsDriverInterface
{
    /**
     * Parsea un frame binario del protocolo del fabricante.
     *
     * @return Position[]  Una o varias posiciones contenidas en el frame.
     */
    public function parse(string $binaryData): array;

    /** Nombre/identificador de la marca (coincide con enum fleet_devices.brand). */
    public function name(): string;

    /** Detecta si un handshake/paquete inicial pertenece a esta marca. */
    public function supports(string $handshake): bool;
}
