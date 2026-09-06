<?php

namespace App\Modules\Addons\MapaRed\Services;

use App\Modules\Addons\MapaRed\Models\MapaRedCable;
use App\Modules\Addons\MapaRed\Models\MapaRedLayer;

/**
 * MR-24c (item #9990436): snap en servidor al hacer clic en el mapa — busca el elemento puntual
 * o el cable/troncal más cercano dentro de un radio (default 15m), para enganchar ahí una NAP
 * nueva en vez de dejarla flotando a un par de metros del punto real.
 *
 * Discontinuidad resuelta (ver spec del item, #960): hoy conviven DOS representaciones de
 * "cable/troncal" — mapared_layers(dialog='route') legacy (con selección MANUAL en el UI de
 * Troncales, radio 200m, ver AvaiablesRoutesComponent.vue + LayersController::avaiablesRoutes) y
 * MapaRedCable nuevo de MR-09/#945 (mapared_cables, con numero_hilos). DECISIÓN: `cableMasCercano()`
 * gobierna sobre MapaRedCable — es la representación que el patrón de nomenclatura D24
 * (T-{ZONA}-FO{HILOS}-{N}) necesita, porque `numero_hilos` solo existe ahí. `route` en
 * mapared_layers queda fuera de ambos métodos de este servicio: no es un elemento puntual (su
 * `coords` es una polilínea, no {lat,lng}) y no es la entidad de cable que este snap gobierna;
 * mapared_cables sigue vacía en dev (2026-09-06) porque MR-09 todavía no tiene un flujo que la
 * pueble desde el mapa — cuando lo tenga, este servicio ya queda listo para usarse.
 */
class SnapService
{
    /**
     * Elemento puntual más cercano (NAP, mufa, poste, cliente, etc.) dentro de $metros.
     * Excluye dialog='region' (zonas, son polígonos) por instrucción explícita del spec; de paso,
     * cualquier dialog cuyo `coords` no sea un punto {lat,lng} (p.ej. 'route', que es una
     * polilínea) queda fuera solo porque el prefiltro por JSON_EXTRACT('$.lat') no matchea ahí.
     */
    public static function elementoMasCercano(float $lat, float $lng, float $metros = 15): ?array
    {
        [$deltaLat, $deltaLng] = self::deltaGrados($lat, $metros);

        $candidatos = MapaRedLayer::query()
            ->without('service_box')
            ->where('dialog', '!=', 'region')
            ->whereRaw("(JSON_EXTRACT(coords, '$.lat') + 0) BETWEEN ? AND ?", [$lat - $deltaLat, $lat + $deltaLat])
            ->whereRaw("(JSON_EXTRACT(coords, '$.lng') + 0) BETWEEN ? AND ?", [$lng - $deltaLng, $lng + $deltaLng])
            ->get(['id', 'dialog', 'text', 'label', 'coords']);

        $mejor = null;
        $mejorDistancia = null;

        foreach ($candidatos as $layer) {
            $coords = $layer->coords;
            if (!is_array($coords) || !isset($coords['lat'], $coords['lng'])) {
                continue;
            }

            $distancia = self::haversineMetros($lat, $lng, (float) $coords['lat'], (float) $coords['lng']);

            if ($distancia > $metros) {
                continue;
            }

            if ($mejorDistancia === null || $distancia < $mejorDistancia) {
                $mejorDistancia = $distancia;
                $mejor = [
                    'id' => $layer->id,
                    'dialog' => $layer->dialog,
                    'text' => $layer->text,
                    'label' => $layer->label,
                    'lat' => (float) $coords['lat'],
                    'lng' => (float) $coords['lng'],
                    'distancia_metros' => round($distancia, 2),
                ];
            }
        }

        return $mejor;
    }

    /**
     * Cable/troncal más cercano dentro de $metros: distancia punto-a-segmento sobre el
     * LineString de `geom_json` (coordinates en [lng,lat], igual que CableStructureService).
     * bbox_* precalculado se usa como prefiltro barato cuando está poblado; si un cable no lo
     * tiene (hoy es el caso general — nada lo backfillea todavía, ver nota de clase) se incluye
     * igual y se mide exacto, para no dejar de encontrar cables reales solo por un bbox vacío.
     */
    public static function cableMasCercano(float $lat, float $lng, float $metros = 15): ?array
    {
        [$deltaLat, $deltaLng] = self::deltaGrados($lat, $metros);
        $minLat = $lat - $deltaLat;
        $maxLat = $lat + $deltaLat;
        $minLng = $lng - $deltaLng;
        $maxLng = $lng + $deltaLng;

        $candidatos = MapaRedCable::query()
            ->whereNotNull('geom_json')
            ->where(function ($q) use ($minLat, $maxLat, $minLng, $maxLng) {
                $q->whereNull('bbox_min_lat')
                    ->orWhere(function ($q2) use ($minLat, $maxLat, $minLng, $maxLng) {
                        $q2->where('bbox_min_lat', '<=', $maxLat)
                            ->where('bbox_max_lat', '>=', $minLat)
                            ->where('bbox_min_lng', '<=', $maxLng)
                            ->where('bbox_max_lng', '>=', $minLng);
                    });
            })
            ->get(['id', 'nombre', 'codigo_tipo', 'numero_hilos', 'geom_json']);

        $mejor = null;
        $mejorDistancia = null;

        foreach ($candidatos as $cable) {
            $geo = json_decode($cable->geom_json, true);
            $coords = $geo['coordinates'] ?? null;

            if (!is_array($coords) || count($coords) < 2) {
                continue;
            }

            for ($i = 1; $i < count($coords); $i++) {
                [$lng1, $lat1] = $coords[$i - 1];
                [$lng2, $lat2] = $coords[$i];

                $distancia = self::distanciaPuntoSegmentoMetros(
                    $lat,
                    $lng,
                    (float) $lat1,
                    (float) $lng1,
                    (float) $lat2,
                    (float) $lng2
                );

                if ($distancia > $metros) {
                    continue;
                }

                if ($mejorDistancia === null || $distancia < $mejorDistancia) {
                    $mejorDistancia = $distancia;
                    $mejor = [
                        'id' => $cable->id,
                        'nombre' => $cable->nombre,
                        'codigo_tipo' => $cable->codigo_tipo,
                        'numero_hilos' => $cable->numero_hilos,
                        'distancia_metros' => round($distancia, 2),
                    ];
                }
            }
        }

        return $mejor;
    }

    /**
     * Convierte un radio en metros a un delta en grados lat/lng, centrado en $lat (para no
     * sub/sobre-estimar el ancho en longitud lejos del ecuador). Mismo enfoque de bbox barato
     * que usa Flotas para sus geocercas (PointInPolygon::boundingBox()).
     */
    private static function deltaGrados(float $lat, float $metros): array
    {
        $metrosPorGradoLat = 111320.0;
        $metrosPorGradoLng = max(1.0, 111320.0 * cos(deg2rad($lat)));

        return [$metros / $metrosPorGradoLat, $metros / $metrosPorGradoLng];
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

    /**
     * Distancia de un punto a un segmento, proyectando a metros planos localmente (equirectangular
     * centrado en el segmento) — adecuado para segmentos cortos de red de fibra, sin PostGIS (D8).
     */
    private static function distanciaPuntoSegmentoMetros(
        float $lat,
        float $lng,
        float $lat1,
        float $lng1,
        float $lat2,
        float $lng2
    ): float {
        $refLat = deg2rad(($lat1 + $lat2) / 2);
        $mPerDegLat = 111320.0;
        $mPerDegLng = 111320.0 * cos($refLat);

        $x1 = 0.0;
        $y1 = 0.0;
        $x2 = ($lng2 - $lng1) * $mPerDegLng;
        $y2 = ($lat2 - $lat1) * $mPerDegLat;
        $px = ($lng - $lng1) * $mPerDegLng;
        $py = ($lat - $lat1) * $mPerDegLat;

        $dx = $x2 - $x1;
        $dy = $y2 - $y1;
        $lenSq = $dx * $dx + $dy * $dy;

        if ($lenSq <= 0.0) {
            return sqrt($px ** 2 + $py ** 2);
        }

        $t = max(0.0, min(1.0, (($px - $x1) * $dx + ($py - $y1) * $dy) / $lenSq));
        $projX = $x1 + $t * $dx;
        $projY = $y1 + $t * $dy;

        return sqrt(($px - $projX) ** 2 + ($py - $projY) ** 2);
    }
}
