<?php

namespace App\Modules\Addons\MapaRed\Services;

/**
 * MR-25 Fase 3a (item #9990443) — parseo de GeoJSON, hermano de `KmlParserService` para el
 * mismo contrato preview/commit de `ImportadorRedService`. Aplana un FeatureCollection a la
 * MISMA forma de placemark que produce `KmlParserService::aplanar()` (name/type/coords/data/
 * extended_data/folder_path), para que `ImportadorRedService::clasificarPlacemark()` los
 * consuma sin distinguir el origen.
 *
 * Alcance: geometrías Point/LineString/Polygon (igual que KML). Point/MultiPolygon/
 * GeometryCollection y otras variantes "Multi*" quedan fuera (mismo alcance que KML, que
 * tampoco soporta MultiGeometry con más de un tipo mezclado); una feature con geometría no
 * soportada se descarta en el aplanado (equivalente a "no_soportado" en el reporte).
 */
class GeoJsonParserService
{
    /**
     * Lee y decodifica el archivo subido, validando que sea un FeatureCollection o Feature
     * GeoJSON válido. Guard explícito contra JSON malformado (el riesgo señalado por el
     * revisor al aprobar el item): un archivo corrupto lanza excepción clara en vez de
     * tronar más abajo con un array_map sobre `null`.
     */
    public static function decodificarArchivo(string $path): array
    {
        $contenido = file_get_contents($path);
        if ($contenido === false || trim($contenido) === '') {
            throw new \InvalidArgumentException('El archivo GeoJSON está vacío o no se pudo leer');
        }

        $geojson = json_decode($contenido, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($geojson)) {
            throw new \InvalidArgumentException('El archivo no es un GeoJSON válido (JSON malformado)');
        }

        if (!in_array($geojson['type'] ?? null, ['FeatureCollection', 'Feature'], true)) {
            throw new \InvalidArgumentException('El GeoJSON debe ser de tipo FeatureCollection o Feature');
        }

        return $geojson;
    }

    /**
     * @return array lista plana de placemarks, misma forma que `KmlParserService::aplanar()`.
     */
    public static function aplanar(array $geojson): array
    {
        $features = $geojson['type'] === 'FeatureCollection'
            ? ($geojson['features'] ?? [])
            : [$geojson];

        $placemarks = [];
        foreach ($features as $feature) {
            $placemark = self::featureAPlacemark(is_array($feature) ? $feature : []);
            if ($placemark !== null) {
                $placemarks[] = $placemark;
            }
        }

        return $placemarks;
    }

    private static function featureAPlacemark(array $feature): ?array
    {
        $geometry = $feature['geometry'] ?? null;
        if (!is_array($geometry) || !isset($geometry['type'])) {
            return null;
        }

        $tipoNormalizado = match ($geometry['type']) {
            'Point' => 'marker',
            'LineString' => 'polyline',
            'Polygon' => 'polygon',
            default => null,
        };
        if ($tipoNormalizado === null) {
            return null;
        }

        $coords = self::normalizarCoords($tipoNormalizado, $geometry['coordinates'] ?? null);
        if ($coords === null) {
            return null;
        }

        $properties = $feature['properties'] ?? [];
        if (!is_array($properties)) {
            $properties = [];
        }
        $nombre = $properties['name'] ?? $properties['title'] ?? '';

        return [
            'name' => is_string($nombre) ? $nombre : (string) $nombre,
            'type' => $tipoNormalizado,
            'coords' => $coords,
            'folder_path' => [],
            'data' => ['description' => $properties['description'] ?? null],
            'extended_data' => $properties,
        ];
    }

    private static function normalizarCoords(string $tipo, mixed $coordinates): mixed
    {
        return match ($tipo) {
            'marker' => self::coordAPunto($coordinates),
            'polyline' => self::coordsALinea($coordinates),
            'polygon' => self::coordsAPoligono($coordinates),
            default => null,
        };
    }

    /**
     * GeoJSON usa orden [lng, lat, alt?] (mismo orden que las coordenadas KML "lon,lat,alt").
     */
    private static function coordAPunto(mixed $coord): ?array
    {
        if (!is_array($coord) || count($coord) < 2 || !is_numeric($coord[0]) || !is_numeric($coord[1])) {
            return null;
        }

        return ['lat' => (float) $coord[1], 'lng' => (float) $coord[0]];
    }

    private static function coordsALinea(mixed $coordinates): ?array
    {
        if (!is_array($coordinates)) {
            return null;
        }

        $puntos = [];
        foreach ($coordinates as $coord) {
            $punto = self::coordAPunto($coord);
            if ($punto !== null) {
                $puntos[] = $punto;
            }
        }

        return count($puntos) > 1 ? $puntos : null;
    }

    /**
     * Polygon.coordinates es un array de anillos (el primero es el exterior); igual que
     * `KmlParserService`, solo se toma el anillo exterior (sin soporte de huecos/interior).
     */
    private static function coordsAPoligono(mixed $coordinates): ?array
    {
        if (!is_array($coordinates) || !isset($coordinates[0])) {
            return null;
        }

        return self::coordsALinea($coordinates[0]);
    }
}
