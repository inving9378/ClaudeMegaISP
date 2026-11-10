<?php

namespace App\Modules\Addons\MapaRed\Services;

use App\Modules\Addons\MapaRed\Models\MapaRedLayer;
use App\Modules\Addons\MapaRed\Models\MapaRedPotenciaLectura;
use Illuminate\Support\Carbon;

/**
 * Item #1270 (MR-33) — historial de potencia por caja. Una lectura al día como máximo y solo
 * si la mínima cambió ≥ UMBRAL_CAMBIO_DB contra la última guardada (la primera siempre se guarda).
 * Signo: más negativo = peor.
 */
class MapaRedPotenciaHistorialService
{
    public const UMBRAL_CAMBIO_DB = 0.5;
    public const RETENCION_MESES = 12;

    public function __construct(private MapaRedNapHealthService $salud)
    {
    }

    /** @return string 'guardada'|'sin_lectura'|'sin_cambio'|'ya_hoy' */
    public function capturar(int $layerId, ?Carbon $ahora = null): string
    {
        $ahora = $ahora ?? now();
        $resumen = $this->salud->calcular(MapaRedLayer::class, $layerId);
        if ($resumen['potencia_minima_dbm'] === null) {
            return 'sin_lectura';
        }

        $ultima = MapaRedPotenciaLectura::where('layer_id', $layerId)->orderByDesc('capturada_en')->orderByDesc('id')->first();
        if ($ultima) {
            if ($ultima->capturada_en->isSameDay($ahora)) {
                return 'ya_hoy';
            }
            if (abs($resumen['potencia_minima_dbm'] - $ultima->potencia_minima_dbm) < self::UMBRAL_CAMBIO_DB) {
                return 'sin_cambio';
            }
        }

        MapaRedPotenciaLectura::create([
            'layer_id' => $layerId,
            'potencia_minima_dbm' => $resumen['potencia_minima_dbm'],
            'potencia_promedio_dbm' => $resumen['potencia_promedio_dbm'],
            'total_onus' => $resumen['total_onus'],
            'capturada_en' => $ahora,
        ]);

        return 'guardada';
    }

    /** Borra lecturas con más de RETENCION_MESES. */
    public function purgar(?Carbon $ahora = null): int
    {
        $corte = ($ahora ?? now())->copy()->subMonths(self::RETENCION_MESES);

        return MapaRedPotenciaLectura::where('capturada_en', '<', $corte)->delete();
    }

    /** Serie de los últimos $dias días + diferencia contra la toma de instalación. */
    public function serie(int $layerId, int $dias = 30, ?Carbon $ahora = null): array
    {
        $ahora = $ahora ?? now();
        $layer = MapaRedLayer::findOrFail($layerId);
        $puntos = MapaRedPotenciaLectura::where('layer_id', $layerId)
            ->where('capturada_en', '>=', $ahora->copy()->subDays($dias))
            ->orderBy('capturada_en')
            ->get()
            ->map(fn ($l) => [
                'fecha' => $l->capturada_en->toIso8601String(),
                'minima_dbm' => $l->potencia_minima_dbm,
                'promedio_dbm' => $l->potencia_promedio_dbm,
            ])->values();

        $instalacion = $layer->potencia_instalacion_dbm !== null ? (float) $layer->potencia_instalacion_dbm : null;
        $actual = $puntos->isNotEmpty() ? $puntos->last()['minima_dbm'] : null;
        // Positivo = bajó (empeoró) esa cantidad de dB desde la instalación.
        $bajoDb = ($instalacion !== null && $actual !== null) ? round($instalacion - $actual, 2) : null;

        return [
            'dias' => $dias,
            'puntos' => $puntos,
            'potencia_instalacion_dbm' => $instalacion,
            'potencia_actual_dbm' => $actual,
            'bajo_db' => $bajoDb,
        ];
    }
}
