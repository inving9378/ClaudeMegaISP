<?php

namespace App\Modules\Addons\MapaRed\Services;

use App\Modules\Addons\MapaRed\Models\MapaRedCable;

/**
 * MR-24b (item roadmap #9990433, sub-item de #960) — D23: "zona, proyecto y nomenclatura se
 * heredan del tramo/zona donde cayó el clic". Resuelve zona + proyecto a partir de un punto
 * lat/lng en vivo (clic en el mapa).
 *
 * Decisión (Opción B del propio item — la única de las tres que no exige UI nueva ni datos que
 * hoy no existen en dev, registrada vía circuito:reportar): la única fuente real de "zona por
 * geometría" es `mapared_cables` (columnas `zona`/`lat`/`lng`/`proyecto_id` ya en su esquema
 * desde MR-09/#945). Se resuelve por PROXIMIDAD al cable más cercano dentro de un radio, no por
 * point-in-polygon — la Opción A (`mapared_layers.dialog='region'` como polígono real) tiene 0
 * filas en dev y su UI de dibujo no existe todavía (fuera de alcance de este sub-item: "NO tocar
 * UI todavía").
 *
 * Esta clase NO decide nomenclatura: `NomenclaturaService` (D24) consume la 'zona' que devuelve
 * este servicio, no la recalcula.
 */
class ZonaResolverService
{
    /**
     * Radio de búsqueda por default. Más allá de esto, el cable más cercano ya no describe
     * razonablemente "el tramo/zona donde cayó el clic".
     */
    public const RADIO_METROS_DEFAULT = 300;

    /**
     * @return array{zona: ?string, proyecto_id: ?int, cable_id: int, distancia_metros: float}|null
     *         null si no hay ningún cable con lat/lng dentro del radio.
     */
    public function resolverPorPunto(float $lat, float $lng, int $radioMetros = self::RADIO_METROS_DEFAULT): ?array
    {
        $bbox = $this->bboxParaRadio($lat, $lng, $radioMetros);

        $candidatos = MapaRedCable::query()
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->whereBetween('lat', [$bbox['min_lat'], $bbox['max_lat']])
            ->whereBetween('lng', [$bbox['min_lng'], $bbox['max_lng']])
            ->get(['id', 'zona', 'proyecto_id', 'lat', 'lng']);

        $masCercano = null;
        $distanciaMin = null;

        foreach ($candidatos as $cable) {
            $distancia = $this->haversineMetros($lat, $lng, (float) $cable->lat, (float) $cable->lng);

            if ($distancia > $radioMetros) {
                continue;
            }

            if ($distanciaMin === null || $distancia < $distanciaMin) {
                $distanciaMin = $distancia;
                $masCercano = $cable;
            }
        }

        if (!$masCercano) {
            return null;
        }

        return [
            'zona' => $masCercano->zona,
            'proyecto_id' => $masCercano->proyecto_id,
            'cable_id' => $masCercano->id,
            'distancia_metros' => round($distanciaMin, 2),
        ];
    }

    /**
     * Caja envolvente en grados equivalente a $radioMetros, para acotar los candidatos ANTES del
     * Haversine exacto (mismo patrón de pre-filtro que `Flotas\Services\Geometry\PointInPolygon`).
     */
    private function bboxParaRadio(float $lat, float $lng, int $radioMetros): array
    {
        $deltaLat = $radioMetros / 111320; // 1° lat ≈ 111.32 km, constante en todo el globo
        $deltaLng = $radioMetros / (111320 * max(cos(deg2rad($lat)), 0.01)); // se achica lejos del ecuador

        return [
            'min_lat' => $lat - $deltaLat,
            'max_lat' => $lat + $deltaLat,
            'min_lng' => $lng - $deltaLng,
            'max_lng' => $lng + $deltaLng,
        ];
    }

    private function haversineMetros(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000; // metros

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
