<?php

namespace App\Modules\Core\Clientes\Services;

use Carbon\Carbon;

/**
 * DTO de salida de BillingPauseService::puedePausar(). Sin comportamiento propio,
 * solo transporta el veredicto y los números que la UI necesita mostrar
 * (tarjeta "Pausa de facturación" + estados del botón del mockup).
 */
class ResultadoElegibilidadPausa
{
    public function __construct(
        public bool $elegible,
        public ?string $motivo = null,
        public ?Carbon $proximaFechaDisponible = null,
        public int $pausasEn12Meses = 0,
        public int $mesesPausadosEn12Meses = 0,
        public int $maxMesesSinCuota = 0,
        public int $maxMesesConCuota = 0,
    ) {
    }
}
