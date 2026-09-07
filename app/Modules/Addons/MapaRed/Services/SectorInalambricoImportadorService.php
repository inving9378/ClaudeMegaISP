<?php

namespace App\Modules\Addons\MapaRed\Services;

use App\Modules\Addons\MapaRed\Models\MapaRedSectorInalambrico;

/**
 * MR-26 Fase 3 (item roadmap #9990524) — import de sectores inalámbricos (azimut/apertura/
 * alcance/altura) vía CSV o GeoJSON. Mismo contrato preview/commit de `ImportadorRedService`
 * (parsear sin escribir → confirmar la MISMA lista, posiblemente corregida), pero NO reusa
 * `ImportadorRedService::confirmar()` porque ese método escribe en `mapared_layers` (entidad
 * genérica de dialog) y los sectores son una tabla propia (`mapared_sectores_inalambricos`,
 * decisión de q1 — ver comentario de decisión del item). Sí reusa los PARSERS de bajo nivel
 * (`CsvParserService`/`GeoJsonParserService`) para no reescribir lectura de CSV/GeoJSON ni
 * validación de lat/lng.
 */
class SectorInalambricoImportadorService
{
    /** Alias de encabezado/propiedad aceptados por campo (normalizados, primer match gana). */
    private const ALIAS = [
        'azimut_grados' => ['azimut_grados', 'azimut', 'azimuth'],
        'apertura_grados' => ['apertura_grados', 'apertura', 'beamwidth'],
        'alcance_metros' => ['alcance_metros', 'alcance', 'range_m', 'range'],
        'altura_metros' => ['altura_metros', 'altura', 'height_m', 'height'],
    ];

    public function previsualizarCsv(string $path): array
    {
        $resultado = CsvParserService::parsearArchivo($path);

        if (!empty($resultado['errores'])) {
            return ['errores' => $resultado['errores']];
        }

        return $this->clasificarPlacemarks($resultado['placemarks']);
    }

    public function previsualizarGeoJson(string $path): array
    {
        $geojson = GeoJsonParserService::decodificarArchivo($path);
        $placemarks = GeoJsonParserService::aplanar($geojson);

        return $this->clasificarPlacemarks($placemarks);
    }

    private function clasificarPlacemarks(array $placemarks): array
    {
        $items = array_map(fn($p) => $this->clasificarPlacemark($p), $placemarks);

        $resumen = [
            'total' => count($items),
            'validos' => count(array_filter($items, fn($i) => $i['soportado'] && empty($i['errores']))),
            'con_error' => count(array_filter($items, fn($i) => $i['soportado'] && !empty($i['errores']))),
            'no_soportados' => count(array_filter($items, fn($i) => !$i['soportado'])),
        ];

        return ['items' => $items, 'resumen' => $resumen];
    }

    private function clasificarPlacemark(array $placemark): array
    {
        $tipo = $placemark['type'] ?? null;
        $coords = $placemark['coords'] ?? null;
        $extra = $placemark['extended_data'] ?? [];

        $soportado = $tipo === 'marker' && is_array($coords) && isset($coords['lat'], $coords['lng']);
        if (!$soportado) {
            return [
                'nombre' => $placemark['name'] ?? '',
                'lat' => null,
                'lng' => null,
                'azimut_grados' => null,
                'apertura_grados' => null,
                'alcance_metros' => null,
                'altura_metros' => null,
                'soportado' => false,
                'errores' => ['Geometría no soportada (se requiere un punto/marker)'],
            ];
        }

        $azimut = $this->valorExtra($extra, 'azimut_grados');
        $apertura = $this->valorExtra($extra, 'apertura_grados');
        $alcance = $this->valorExtra($extra, 'alcance_metros');
        $altura = $this->valorExtra($extra, 'altura_metros');

        $errores = [];
        if (!is_numeric($azimut) || (float) $azimut < 0 || (float) $azimut > 360) {
            $errores[] = 'azimut_grados debe ser numérico entre 0 y 360';
        }
        if (!is_numeric($apertura) || (float) $apertura <= 0 || (float) $apertura > 360) {
            $errores[] = 'apertura_grados debe ser numérico entre 0 y 360';
        }
        if (!is_numeric($alcance) || (float) $alcance <= 0) {
            $errores[] = 'alcance_metros debe ser numérico mayor a 0';
        }
        if ($altura !== null && $altura !== '' && !is_numeric($altura)) {
            $errores[] = 'altura_metros debe ser numérico si se especifica';
        }

        return [
            'nombre' => $placemark['name'] ?? '',
            'lat' => (float) $coords['lat'],
            'lng' => (float) $coords['lng'],
            'azimut_grados' => is_numeric($azimut) ? (float) $azimut : null,
            'apertura_grados' => is_numeric($apertura) ? (float) $apertura : null,
            'alcance_metros' => is_numeric($alcance) ? (int) round((float) $alcance) : null,
            'altura_metros' => is_numeric($altura) ? (float) $altura : null,
            'soportado' => true,
            'errores' => $errores,
        ];
    }

    private function valorExtra(array $extra, string $campo): mixed
    {
        foreach (self::ALIAS[$campo] as $alias) {
            if (array_key_exists($alias, $extra) && trim((string) $extra[$alias]) !== '') {
                return $extra[$alias];
            }
        }

        return null;
    }

    /**
     * @param array $items misma forma que `previsualizarCsv()['items']`/`previsualizarGeoJson()['items']`,
     *   con `omitir`=true opcionalmente marcado por elemento.
     */
    public function confirmar(array $items): array
    {
        $ids = [];
        $omitidos = [];

        foreach ($items as $item) {
            if (!($item['soportado'] ?? false) || !empty($item['errores'])) {
                $omitidos[] = ['nombre' => $item['nombre'] ?? '(sin nombre)', 'motivo' => 'datos_invalidos'];
                continue;
            }

            if ($item['omitir'] ?? false) {
                $omitidos[] = ['nombre' => $item['nombre'], 'motivo' => 'omitido_por_usuario'];
                continue;
            }

            $sector = MapaRedSectorInalambrico::create([
                'nombre' => $item['nombre'] ?: 'Sector sin nombre',
                'lat' => $item['lat'],
                'lng' => $item['lng'],
                'azimut_grados' => $item['azimut_grados'],
                'apertura_grados' => $item['apertura_grados'],
                'alcance_metros' => $item['alcance_metros'],
                'altura_metros' => $item['altura_metros'],
                'activo' => true,
            ]);

            $ids[] = $sector->id;
        }

        return ['creados' => count($ids), 'ids_creados' => $ids, 'omitidos' => $omitidos];
    }
}
