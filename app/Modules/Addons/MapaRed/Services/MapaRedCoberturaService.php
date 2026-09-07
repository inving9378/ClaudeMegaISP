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

        $puertosLibresPorNap = MapaRedPuerto::query()
            ->where('puertable_type', MapaRedLayer::class)
            ->where('rol', MapaRedPuerto::ROL_NAP_SALIDA)
            ->where('estado', MapaRedPuerto::ESTADO_LIBRE)
            ->selectRaw('puertable_id, count(*) as puertos_libres')
            ->groupBy('puertable_id')
            ->pluck('puertos_libres', 'puertable_id');

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
