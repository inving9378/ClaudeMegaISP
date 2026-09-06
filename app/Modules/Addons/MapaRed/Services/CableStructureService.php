<?php

namespace App\Modules\Addons\MapaRed\Services;

use App\Modules\Addons\MapaRed\Models\MapaRedCable;
use App\Modules\Addons\MapaRed\Models\MapaRedHilo;
use App\Modules\Addons\MapaRed\Support\FiberColorScheme;
use Illuminate\Support\Facades\DB;

/**
 * MR-09 (item #945): auto-instanciación de buffers e hilos al guardar un cable, y cálculo de
 * longitud real desde el GeoJSON LineString. Patrón blueprint de netbox-fms: el número de hilos
 * y de hilos por buffer del cable determinan cuántas filas de `mapared_hilos` existen.
 */
class CableStructureService
{
    /**
     * Regenera los hilos del cable si el conteo actual no coincide con `numero_hilos`.
     * Idempotente: si ya están instanciados correctamente, no hace nada.
     */
    public static function sincronizarHilos(MapaRedCable $cable): void
    {
        if (!$cable->numero_hilos) {
            return;
        }

        if ($cable->hilos()->count() === $cable->numero_hilos) {
            return;
        }

        $hilosPorBuffer = $cable->hilos_por_buffer ?: 12;
        $totalBuffers = (int) ceil($cable->numero_hilos / $hilosPorBuffer);
        $colores = FiberColorScheme::eiaTia598();
        $totalColores = count($colores);

        DB::transaction(function () use ($cable, $hilosPorBuffer, $totalBuffers, $colores, $totalColores) {
            $cable->hilos()->delete();

            $filas = [];
            $global = 0;
            $now = now();

            for ($buffer = 1; $buffer <= $totalBuffers; $buffer++) {
                $bufferColor = $colores[($buffer - 1) % $totalColores];
                $enEsteBuffer = min($hilosPorBuffer, $cable->numero_hilos - ($buffer - 1) * $hilosPorBuffer);

                for ($numero = 1; $numero <= $enEsteBuffer; $numero++) {
                    $global++;
                    $filas[] = [
                        'cable_id' => $cable->id,
                        'buffer' => $buffer,
                        'buffer_color' => $bufferColor,
                        'numero' => $numero,
                        'numero_global' => $global,
                        'color' => $colores[($numero - 1) % $totalColores],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            MapaRedHilo::insert($filas);
        });
    }

    /**
     * Suma Haversine de cada segmento del LineString (GeoJSON, coordenadas [lng, lat]).
     * Sin PostGIS (D8): es la misma vía que ya usan Flotas/Talento para distancia real.
     */
    public static function calcularLongitudMetros(?string $geomJson): ?float
    {
        if (!$geomJson) {
            return null;
        }

        $geo = json_decode($geomJson, true);
        $coords = $geo['coordinates'] ?? null;

        if (!is_array($coords) || count($coords) < 2) {
            return null;
        }

        $metros = 0.0;
        for ($i = 1; $i < count($coords); $i++) {
            [$lng1, $lat1] = $coords[$i - 1];
            [$lng2, $lat2] = $coords[$i];
            $metros += self::haversineMetros((float) $lat1, (float) $lng1, (float) $lat2, (float) $lng2);
        }

        return round($metros, 2);
    }

    private static function haversineMetros(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000; // metros
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
