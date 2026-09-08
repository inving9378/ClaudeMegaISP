<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Services;

use App\Modules\Addons\DocumentacionCorporativa\Models\DcDiaFestivo;
use Illuminate\Support\Carbon;

/**
 * Plazo maestro de N días HÁBILES (por defecto 180) a partir de una fecha de
 * inicio: excluye sábado/domingo y las fechas del catálogo `dc_dias_festivos`.
 *
 * Convención (decisión de esta fase, sin precedente en el módulo): el plazo
 * empieza a correr al día SIGUIENTE de `fechaInicio` — el día del propio inicio
 * no cuenta como el primer día hábil, igual que un plazo legal que corre "a
 * partir del día siguiente" al hecho que lo dispara.
 */
class PlazoHabilService
{
    /**
     * @return array{fecha_limite: string, dias_restantes: int, vencido: bool}|null
     */
    public function calcularDiasRestantes(?Carbon $fechaInicio, int $plazoDiasHabiles = 180): ?array
    {
        if ($fechaInicio === null) {
            return null;
        }

        // Una sola query para todo el cómputo, no una por día recorrido.
        $festivos = DcDiaFestivo::pluck('fecha')
            ->map(fn ($fecha) => Carbon::parse($fecha)->toDateString())
            ->flip();

        $fechaLimite = $fechaInicio->copy()->startOfDay();
        $contados    = 0;

        while ($contados < $plazoDiasHabiles) {
            $fechaLimite->addDay();

            if ($fechaLimite->isWeekend()) {
                continue;
            }

            if (isset($festivos[$fechaLimite->toDateString()])) {
                continue;
            }

            $contados++;
        }

        $diasRestantes = (int) now()->startOfDay()->diffInDays($fechaLimite, false);

        return [
            'fecha_limite'   => $fechaLimite->toDateString(),
            'dias_restantes' => $diasRestantes,
            'vencido'        => $diasRestantes < 0,
        ];
    }
}
