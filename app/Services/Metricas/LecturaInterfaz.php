<?php

namespace App\Services\Metricas;

/**
 * Contadores crudos de una interfaz tal como los da el router (rx/tx en su perspectiva).
 * `leidoEn` es el instante Unix (con decimales) de la lectura.
 */
final class LecturaInterfaz
{
    public function __construct(
        public readonly int $rxByte,
        public readonly int $txByte,
        public readonly int $rxPacket,
        public readonly int $txPacket,
        public readonly bool $running,
        public readonly float $leidoEn,
    ) {
    }
}
