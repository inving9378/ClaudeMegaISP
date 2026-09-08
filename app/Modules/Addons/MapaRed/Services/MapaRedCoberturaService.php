<?php

namespace App\Modules\Addons\MapaRed\Services;

use App\Modules\Addons\MapaRed\Models\MapaRedLayer;
use App\Modules\Addons\MapaRed\Models\MapaRedPuerto;

/**
 * MR-26 Fase 1 (item roadmap #9990522) — motor de cobertura: capa GeoJSON EN VIVO (sin
 * persistir/cachear) con un círculo aproximado alrededor de cada NAP con >=1 puerto libre.
 * "Recalcular cuando cambia la ocupación" se cumple gratis: no hay caché ni hooks, cada llamada
 * relee mapared_puertos. Reusa el mismo query base de MapaRedNapOcupacionService::calcular()
 * (delDueno + rol nap_salida) para listar NAPs con puertos libres, y el mismo despeje
 * grados-por-metro de ZonaResolverService::bboxParaRadio() para generar el círculo.
 *
 * Decisión de diseño (ya tomada en el spec del item, aplicada tal cual — ver
 * circuito:reportar --tipo=decision): NO se calcula la unión booleana real de los círculos
 * superpuestos (requeriría una librería de geometría que no existe en el proyecto). Leaflet
 * dibuja los círculos superpuestos y el efecto visual de unión es idéntico; la consulta "¿hay
 * cobertura en este punto?" (Fase 2) tampoco necesita el polígono unido, solo distancia<=radio
 * a la NAP libre más cercana.
 */
class MapaRedCoberturaService
{
    /** Puntos del polígono que aproxima el círculo (sugerido por el spec del item). */
    private const PUNTOS_CIRCULO = 32;

    /**
     * FeatureCollection con un Feature Polygon (círculo aproximado) por cada NAP con >=1 puerto
     * libre. properties = {nap_id, nombre, puertos_libres}.
     */
    public function capaGeoJson(): array
    {
        $radioMetros = (int) config('mapared.cobertura.radio_metros');

        $puertosLibresPorNap = $this->napsConPuertosLibres();

        if ($puertosLibresPorNap->isEmpty()) {
            return ['type' => 'FeatureCollection', 'features' => []];
        }

        $naps = MapaRedLayer::query()
            ->whereIn('id', $puertosLibresPorNap->keys())
            ->get(['id', 'coords', 'data', 'label']);

        $features = [];

        foreach ($naps as $nap) {
            $coords = $nap->coords;
            if (!is_array($coords) || !isset($coords['lat'], $coords['lng'])) {
                continue;
            }

            $features[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Polygon',
                    'coordinates' => [$this->circulo((float) $coords['lat'], (float) $coords['lng'], $radioMetros)],
                ],
                'properties' => [
                    'nap_id' => $nap->id,
                    'nombre' => $nap->text_node ?? sprintf('NAP #%d', $nap->id),
                    'puertos_libres' => (int) $puertosLibresPorNap[$nap->id],
                ],
            ];
        }

        return ['type' => 'FeatureCollection', 'features' => $features];
    }

    /**
     * MR-26 Fase 2 (item roadmap #9990577) — "¿hay cobertura vendible en este punto?". Busca la
     * NAP con puerto libre más cercana a (lat, lng) y compara su distancia contra el radio
     * configurado. Reusa napsConPuertosLibres() (misma query que capaGeoJson()) y la fórmula
     * Haversine de ZonaResolverService::haversineMetros.
     *
     * Decisión de simplificación (ver circuito:reportar --tipo=decision): a diferencia de
     * ZonaResolverService (que descarta candidatos fuera de un radio fijo), aquí SIEMPRE se
     * necesita la NAP libre más cercana exista o no cobertura, así que el prefiltro por bbox
     * (max(radio_metros configurado, 2000) metros) no se expande en anillos si no encuentra
     * nada: cae directo a evaluar TODAS las NAPs con puerto libre (subconjunto ya chico frente a
     * las ~1660 NAPs totales), sin necesidad de una búsqueda expandida más elaborada.
     *
     * @return array{cobertura: bool, nap_mas_cercana: null|array{id: int, nombre: string, puertos_libres: int, distancia_metros: float}}
     */
    public function consultarPunto(float $lat, float $lng): array
    {
        $puertosLibresPorNap = $this->napsConPuertosLibres();

        if ($puertosLibresPorNap->isEmpty()) {
            return ['cobertura' => false, 'nap_mas_cercana' => null];
        }

        $naps = MapaRedLayer::query()
            ->whereIn('id', $puertosLibresPorNap->keys())
            ->get(['id', 'coords', 'data', 'label']);

        $candidatasConCoords = [];
        foreach ($naps as $nap) {
            $coords = $nap->coords;
            if (!is_array($coords) || !isset($coords['lat'], $coords['lng'])) {
                continue;
            }
            $candidatasConCoords[] = $nap;
        }

        // Prefiltro bbox (en PHP, ya que coords vive en una columna JSON sin índice
        // explotable): si nada cae dentro, se evalúan TODAS las candidatas con coords válidas
        // (ver decisión de simplificación en el docblock de este método).
        $radioMetros = (int) config('mapared.cobertura.radio_metros');
        $bbox = $this->bboxParaRadio($lat, $lng, max($radioMetros, 2000));

        $candidatas = array_filter($candidatasConCoords, function ($nap) use ($bbox) {
            $coords = $nap->coords;
            return $coords['lat'] >= $bbox['min_lat'] && $coords['lat'] <= $bbox['max_lat']
                && $coords['lng'] >= $bbox['min_lng'] && $coords['lng'] <= $bbox['max_lng'];
        });

        if (empty($candidatas)) {
            $candidatas = $candidatasConCoords;
        }

        $napMasCercana = null;
        $distanciaMin = null;

        foreach ($candidatas as $nap) {
            $coords = $nap->coords;
            $distancia = $this->haversineMetros($lat, $lng, (float) $coords['lat'], (float) $coords['lng']);

            if ($distanciaMin === null || $distancia < $distanciaMin) {
                $distanciaMin = $distancia;
                $napMasCercana = $nap;
            }
        }

        if (!$napMasCercana) {
            return ['cobertura' => false, 'nap_mas_cercana' => null];
        }

        $nombre = $napMasCercana->text_node ?? sprintf('NAP #%d', $napMasCercana->id);

        return [
            'cobertura' => $distanciaMin <= $radioMetros,
            'nap_mas_cercana' => [
                'id' => $napMasCercana->id,
                'nombre' => $nombre,
                'puertos_libres' => (int) $puertosLibresPorNap[$napMasCercana->id],
                'distancia_metros' => round($distanciaMin, 2),
            ],
        ];
    }

    /**
     * Cuenta puertos libres de rol NAP_SALIDA agrupados por NAP. Query compartida por
     * capaGeoJson() y consultarPunto() — NO duplicar.
     *
     * @return \Illuminate\Support\Collection<int, int> puertos_libres keyed por puertable_id
     */
    private function napsConPuertosLibres()
    {
        return MapaRedPuerto::query()
            ->where('puertable_type', MapaRedLayer::class)
            ->where('rol', MapaRedPuerto::ROL_NAP_SALIDA)
            ->where('estado', MapaRedPuerto::ESTADO_LIBRE)
            ->selectRaw('puertable_id, count(*) as puertos_libres')
            ->groupBy('puertable_id')
            ->pluck('puertos_libres', 'puertable_id');
    }

    /**
     * Caja envolvente en grados equivalente a $radioMetros (mismo patrón de prefiltro que
     * ZonaResolverService::bboxParaRadio).
     *
     * @return array{min_lat: float, max_lat: float, min_lng: float, max_lng: float}
     */
    private function bboxParaRadio(float $lat, float $lng, int $radioMetros): array
    {
        $deltaLat = $radioMetros / 111320;
        $deltaLng = $radioMetros / (111320 * max(cos(deg2rad($lat)), 0.01));

        return [
            'min_lat' => $lat - $deltaLat,
            'max_lat' => $lat + $deltaLat,
            'min_lng' => $lng - $deltaLng,
            'max_lng' => $lng + $deltaLng,
        ];
    }

    /**
     * Misma fórmula exacta que ZonaResolverService::haversineMetros.
     */
    private function haversineMetros(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Anillo cerrado de puntos [lng, lat] (orden GeoJSON) aproximando un círculo de
     * $radioMetros alrededor de (lat, lng).
     *
     * @return array<int, array{0: float, 1: float}>
     */
    private function circulo(float $lat, float $lng, int $radioMetros): array
    {
        $puntos = [];

        for ($i = 0; $i <= self::PUNTOS_CIRCULO; $i++) {
            $anguloRad = 2 * M_PI * ($i / self::PUNTOS_CIRCULO);
            $puntos[] = $this->puntoDesplazado($lat, $lng, $radioMetros, $anguloRad);
        }

        return $puntos;
    }

    /**
     * Desplaza (lat, lng) por $radioMetros en la dirección $anguloRad. Mismo factor
     * metros-por-grado que ZonaResolverService::bboxParaRadio (111320 m/° de latitud,
     * comprimido en longitud por el coseno de la latitud).
     *
     * @return array{0: float, 1: float} [lng, lat]
     */
    private function puntoDesplazado(float $lat, float $lng, float $radioMetros, float $anguloRad): array
    {
        $deltaLat = ($radioMetros * cos($anguloRad)) / 111320;
        $deltaLng = ($radioMetros * sin($anguloRad)) / (111320 * max(cos(deg2rad($lat)), 0.01));

        return [$lng + $deltaLng, $lat + $deltaLat];
    }
}
