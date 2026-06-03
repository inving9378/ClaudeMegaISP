<?php

namespace App\Modules\Addons\Flotas\Services\Geometry;

/**
 * Sub-fase 3.2 — Geometría de geocercas.
 *
 * Convención de coordenadas: SIEMPRE [lat, lng] (consistente con BD `fleet_geofences.polygon`
 * y `fleet_positions.lat/lng`). NO [lng, lat].
 */
class PointInPolygon
{
    /**
     * ¿El punto está dentro del polígono? Algoritmo ray casting estándar
     * (crossing number / even-odd rule). El polígono se cierra implícitamente
     * (el último vértice se conecta con el primero).
     *
     * Comportamiento en el borde: indefinido (puede dar true o false según la arista).
     * Para detección de geocercas es irrelevante: un ping rara vez cae exacto en el borde.
     *
     * @param array $point   [lat, lng]
     * @param array $polygon array de [lat, lng]
     */
    public static function contains(array $point, array $polygon): bool
    {
        $n = count($polygon);
        if ($n < 3) {
            return false; // un polígono necesita al menos 3 vértices
        }

        // Mapeo: x = lng, y = lat (el eje no importa mientras sea consistente).
        $y = (float) $point[0]; // lat
        $x = (float) $point[1]; // lng

        $inside = false;
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $yi = (float) $polygon[$i][0];
            $xi = (float) $polygon[$i][1];
            $yj = (float) $polygon[$j][0];
            $xj = (float) $polygon[$j][1];

            $intersects = (($yi > $y) !== ($yj > $y))
                && ($x < ($xj - $xi) * ($y - $yi) / (($yj - $yi) ?: 1e-12) + $xi);

            if ($intersects) {
                $inside = !$inside;
            }
        }

        return $inside;
    }

    /**
     * Caja envolvente del polígono. Pre-filtro barato antes del ray casting.
     *
     * @param array $polygon array de [lat, lng]
     * @return array{min_lat: float, max_lat: float, min_lng: float, max_lng: float}|null
     */
    public static function boundingBox(array $polygon): ?array
    {
        if (count($polygon) < 3) {
            return null;
        }

        $lats = array_map(fn($p) => (float) $p[0], $polygon);
        $lngs = array_map(fn($p) => (float) $p[1], $polygon);

        return [
            'min_lat' => min($lats),
            'max_lat' => max($lats),
            'min_lng' => min($lngs),
            'max_lng' => max($lngs),
        ];
    }

    /**
     * ¿El punto cae dentro de la bounding box? (rápido, sin trigonometría)
     *
     * @param array $point [lat, lng]
     * @param array{min_lat: float, max_lat: float, min_lng: float, max_lng: float}|null $bbox
     */
    public static function inBoundingBox(array $point, ?array $bbox): bool
    {
        if (!$bbox) {
            return false;
        }

        $lat = (float) $point[0];
        $lng = (float) $point[1];

        return $lat >= $bbox['min_lat'] && $lat <= $bbox['max_lat']
            && $lng >= $bbox['min_lng'] && $lng <= $bbox['max_lng'];
    }
}
