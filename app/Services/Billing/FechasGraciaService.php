<?php

namespace App\Services\Billing;

use Carbon\Carbon;

/**
 * Regla única de fechas del calendario de facturación/gracia (item #1143).
 * - Día de corte inexistente en el mes (29-31) => último día real del mes (sin desbordar).
 * - Gracia 0 => fin de gracia el mismo día de facturación (corte al cierre de ese día).
 */
class FechasGraciaService
{
    public static function diaFacturacion(int $anio, int $mes, int $dia): Carbon
    {
        $base = Carbon::createFromDate($anio, $mes, 1);
        $dia = max(1, min($dia, $base->daysInMonth));

        return $base->day($dia)->startOfDay();
    }

    public static function finGracia(Carbon $facturacion, int $diasGracia): Carbon
    {
        return $facturacion->copy()->addDays(max(0, $diasGracia));
    }
}
