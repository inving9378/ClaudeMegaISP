<?php

namespace App\Modules\Addons\MapaRed\Services;

use App\Modules\Addons\MapaRed\Models\MapaRedLayer;
use Illuminate\Support\Str;

/**
 * MR-25 (item #961) — importador KML/KMZ con detección de tipo, duplicados y reporte de
 * omitidos. DoD del item: importar un KMZ real exportado del Google Earth de Meganet y que
 * los elementos caigan en su lugar con su tipo correcto.
 *
 * Flujo en DOS pasos, sin estado en servidor (nada de sesión/archivo temporal):
 * 1) `previsualizar()` — parsea el archivo y devuelve los elementos con el tipo detectado y
 *    los posibles duplicados, SIN escribir nada en BD.
 * 2) `confirmar()` — recibe la MISMA lista de items (el cliente pudo corregir el `tipo` de
 *    alguno o marcar `omitir`) y recién ahí escribe. Reusa el patrón de bulk-insert de
 *    `KMZController::saveLayersFromNode` (raw insert, sin disparar los eventos `boot()` de
 *    `MapaRedLayer` como `createCharolas()`/`createFibers()` — igual que hace hoy el import
 *    genérico existente; cablear charolas/hilos automáticos desde el importador queda fuera
 *    de alcance de #961, es trabajo de MR-08+ sobre el catálogo de materiales).
 *
 * Alcance de #961 Fase 1 (KML/KMZ): GeoJSON y CSV quedan para items de seguimiento (mismo
 * contrato preview/commit, solo cambia el parser de entrada).
 */
class ImportadorRedService
{
    /**
     * Vocabulario de tipos detectables — espejo server-side de `menuOptions` en
     * resources/js/components/module/mapared/helper/mapUtils.js (misma terminología que ya
     * usa la UI: "Caja de servicio" = NAP, "Caja de empalme" = mufa). Orden = prioridad de
     * coincidencia (el primer match de la lista gana).
     */
    private const TIPOS_DETECTABLES = [
        'service_box' => [
            'keywords' => ['caja de servicio', 'caja servicio', 'nap', 'csp', 'cto'],
            'text' => 'Caja de servicio',
            'icon' => 'mdi-package',
            'route' => 'serviceboxs',
        ],
        'junction_box' => [
            'keywords' => ['caja de empalme', 'caja empalme', 'mufa', 'empalme', 'junction'],
            'text' => 'Caja de empalme',
            'icon' => 'mdi-package-variant-closed',
            'route' => 'junctionboxs',
        ],
        'cupboard' => [
            'keywords' => ['armario', 'gabinete', 'cupboard'],
            'text' => 'Armario',
            'icon' => 'mdi-cupboard-outline',
            'route' => 'cupboards',
        ],
        'pole' => [
            'keywords' => ['poste', 'pole'],
            'text' => 'Poste',
            'icon' => 'mdi-currency-mnt',
            'route' => 'poles',
        ],
        'source' => [
            'keywords' => ['fuente', 'olt', 'source'],
            'text' => 'Fuente',
            'icon' => 'mdi-flash-outline',
            'route' => 'sources',
        ],
        'site' => [
            'keywords' => ['sitio', 'site', 'torre'],
            'text' => 'Site',
            'icon' => 'mdi-warehouse',
            'route' => 'sites',
        ],
    ];

    private const TIPO_GENERICO_MARKER = [
        'dialog' => 'kmz',
        'text' => 'Objeto KMZ',
        'icon' => 'mdi-map-marker',
        'route' => 'kmz',
    ];

    private const RADIO_DUPLICADO_METROS = 5.0;

    public function previsualizar(string $path, ?string $mimeType): array
    {
        $kml = KmlParserService::extraerKmlDeArchivo($path, $mimeType);
        $tree = KmlParserService::parseKmlToJson($kml);
        $placemarks = KmlParserService::aplanar($tree);

        return $this->clasificarPlacemarks($placemarks);
    }

    /**
     * MR-25 Fase 3a (item #9990443) — mismo contrato de `previsualizar()`, solo cambia el
     * parser de entrada (GeoJSON en vez de KML/KMZ). `clasificarPlacemark()`/`buscarDuplicado()`
     * son formato-agnósticas, se reusan tal cual.
     */
    public function previsualizarGeoJson(string $path): array
    {
        $geojson = GeoJsonParserService::decodificarArchivo($path);
        $placemarks = GeoJsonParserService::aplanar($geojson);

        return $this->clasificarPlacemarks($placemarks);
    }

    private function clasificarPlacemarks(array $placemarks): array
    {
        $items = [];
        foreach ($placemarks as $placemark) {
            $items[] = $this->clasificarPlacemark($placemark);
        }

        $resumen = [
            'total' => count($items),
            'nuevos' => count(array_filter($items, fn($i) => $i['soportado'] && !($i['duplicado']['exacto'] ?? false))),
            'duplicados' => count(array_filter($items, fn($i) => $i['duplicado']['exacto'] ?? false)),
            'cercanos' => count(array_filter($items, fn($i) => $i['duplicado'] !== null && !($i['duplicado']['exacto'] ?? false))),
            'no_soportados' => count(array_filter($items, fn($i) => !$i['soportado'])),
        ];

        return ['items' => $items, 'resumen' => $resumen];
    }

    /**
     * @param array $items misma forma que devuelve `previsualizar()['items']`, con `tipo`
     *   posiblemente corregido a mano y/o `omitir`=true por elemento.
     */
    public function confirmar(array $items, ?int $projectId): array
    {
        $rows = [];
        $omitidos = [];

        foreach ($items as $item) {
            if (!($item['soportado'] ?? false)) {
                $omitidos[] = ['nombre' => $item['nombre'] ?? '(sin nombre)', 'motivo' => 'geometria_no_soportada'];
                continue;
            }

            if ($item['omitir'] ?? false) {
                $omitidos[] = ['nombre' => $item['nombre'], 'motivo' => 'omitido_por_usuario'];
                continue;
            }

            $geometria = $item['geometria'] ?? [];
            $tipo = $item['tipo'] ?? [];
            $dialog = $tipo['dialog'] ?? null;

            if (($geometria['type'] ?? null) === 'marker' && !($item['forzar_duplicado'] ?? false)) {
                $duplicado = $this->buscarDuplicado(
                    (float) ($geometria['coords']['lat'] ?? 0),
                    (float) ($geometria['coords']['lng'] ?? 0),
                    $item['nombre'] ?? ''
                );
                if ($duplicado !== null && $duplicado['exacto']) {
                    $omitidos[] = ['nombre' => $item['nombre'], 'motivo' => 'duplicado', 'detalle' => $duplicado];
                    continue;
                }
            }

            if (!$this->tipoValido($dialog)) {
                $tipo = $this->tipoGenericoParaGeometria($geometria['type'] ?? null);
                $dialog = $tipo['dialog'];
            }

            $rows[] = [
                'project_id' => $projectId,
                'classification' => 'project',
                'type' => $geometria['type'] ?? 'marker',
                'color' => '#6666ff',
                'route' => $tipo['route'] ?? $dialog,
                'dialog' => $dialog,
                'text' => $tipo['text'] ?? 'Objeto KMZ',
                'icon' => $tipo['icon'] ?? 'mdi-map-marker',
                'icon_color' => '#FFFFFF',
                'weight' => 4,
                'distance' => 0,
                'label' => 'name',
                'coords' => json_encode($geometria['coords'] ?? null),
                'data' => json_encode([
                    'name' => $item['nombre'] ?? '',
                    'description' => $item['descripcion'] ?? null,
                    ...($item['extended_data'] ?? []),
                ]),
                'inputs' => 6,
                'level' => 1000000,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $ids = [];
        foreach ($rows as $row) {
            $ids[] = MapaRedLayer::query()->insertGetId($row);
        }

        return [
            'creados' => count($ids),
            'ids_creados' => $ids,
            'omitidos' => $omitidos,
        ];
    }

    private function clasificarPlacemark(array $placemark): array
    {
        $nombre = $placemark['name'] ?? '';
        $geometryType = $placemark['type'] ?? null;
        $coords = $placemark['coords'] ?? null;
        $folderPath = $placemark['folder_path'] ?? [];

        $soportado = in_array($geometryType, ['marker', 'polyline', 'polygon'], true) && $coords !== null;

        $tipo = match ($geometryType) {
            'marker' => $this->detectarTipo($nombre, $folderPath),
            'polyline' => ['dialog' => 'route', 'text' => 'Ruta', 'icon' => 'mdi-chart-timeline-variant', 'route' => 'route', 'confianza' => 'geometria'],
            'polygon' => ['dialog' => 'region', 'text' => 'Región', 'icon' => 'mdi-vector-polygon', 'route' => 'regions', 'confianza' => 'geometria'],
            default => ['dialog' => null, 'text' => null, 'icon' => null, 'route' => null, 'confianza' => null],
        };

        $duplicado = ($geometryType === 'marker' && $soportado)
            ? $this->buscarDuplicado((float) ($coords['lat'] ?? 0), (float) ($coords['lng'] ?? 0), $nombre)
            : null;

        return [
            'nombre' => $nombre,
            'descripcion' => $placemark['data']['description'] ?? null,
            'folder_path' => $folderPath,
            'geometria' => ['type' => $geometryType, 'coords' => $coords],
            'extended_data' => $placemark['extended_data'] ?? [],
            'tipo' => $tipo,
            'duplicado' => $duplicado,
            'soportado' => $soportado,
            'omitir' => $duplicado['exacto'] ?? false,
        ];
    }

    /**
     * Heurística por palabra clave sobre nombre + ruta de carpetas (ambos normalizados sin
     * acentos/mayúsculas). Es una primera aproximación pensada para corregirse a mano en el
     * paso de mapeo de campos del wizard (fuera de alcance de esta fase, ver items de
     * seguimiento) — por eso `confianza` viaja en la respuesta.
     */
    public function detectarTipo(string $nombre, array $folderPath): array
    {
        $haystack = $this->normalizarTexto($nombre . ' ' . implode(' ', $folderPath));

        foreach (self::TIPOS_DETECTABLES as $dialog => $config) {
            foreach ($config['keywords'] as $keyword) {
                if (str_contains($haystack, $keyword)) {
                    return [
                        'dialog' => $dialog,
                        'text' => $config['text'],
                        'icon' => $config['icon'],
                        'route' => $config['route'],
                        'confianza' => 'alta',
                    ];
                }
            }
        }

        return [
            'dialog' => self::TIPO_GENERICO_MARKER['dialog'],
            'text' => self::TIPO_GENERICO_MARKER['text'],
            'icon' => self::TIPO_GENERICO_MARKER['icon'],
            'route' => self::TIPO_GENERICO_MARKER['route'],
            'confianza' => 'baja',
        ];
    }

    /**
     * Duplicado por proximidad (<5m, D20) Y nombre — ambas señales juntas para no confundir
     * dos elementos reales distintos que quedaron cerca (ej. poste y NAP en la misma base).
     * Si hay algo cerca pero con nombre distinto, se reporta como "cercano" (no se omite
     * solo; el reporte lo deja visible para que un humano decida).
     */
    public function buscarDuplicado(float $lat, float $lng, string $nombre): ?array
    {
        $metros = self::RADIO_DUPLICADO_METROS;
        $metrosPorGradoLat = 111320.0;
        $metrosPorGradoLng = max(1.0, 111320.0 * cos(deg2rad($lat)));
        $deltaLat = $metros / $metrosPorGradoLat;
        $deltaLng = $metros / $metrosPorGradoLng;

        $candidatos = MapaRedLayer::query()
            ->without('service_box')
            ->where('dialog', '!=', 'region')
            ->whereRaw("(JSON_EXTRACT(coords, '$.lat') + 0) BETWEEN ? AND ?", [$lat - $deltaLat, $lat + $deltaLat])
            ->whereRaw("(JSON_EXTRACT(coords, '$.lng') + 0) BETWEEN ? AND ?", [$lng - $deltaLng, $lng + $deltaLng])
            ->get(['id', 'dialog', 'text', 'label', 'data', 'coords']);

        $nombreNormalizado = $this->normalizarTexto($nombre);
        $mejor = null;
        $mejorDistancia = null;

        foreach ($candidatos as $layer) {
            $coords = $layer->coords;
            if (!is_array($coords) || !isset($coords['lat'], $coords['lng'])) {
                continue;
            }

            $distancia = $this->haversineMetros($lat, $lng, (float) $coords['lat'], (float) $coords['lng']);
            if ($distancia > $metros) {
                continue;
            }

            if ($mejorDistancia === null || $distancia < $mejorDistancia) {
                $nombreExistente = is_array($layer->data) ? ($layer->data[$layer->label] ?? '') : '';
                $mejorDistancia = $distancia;
                $mejor = [
                    'id' => $layer->id,
                    'nombre_existente' => $nombreExistente,
                    'distancia_metros' => round($distancia, 2),
                    'exacto' => $this->normalizarTexto($nombreExistente) === $nombreNormalizado && $nombreNormalizado !== '',
                ];
            }
        }

        return $mejor;
    }

    private function tipoValido(?string $dialog): bool
    {
        return $dialog !== null && (
            array_key_exists($dialog, self::TIPOS_DETECTABLES)
            || in_array($dialog, ['kmz', 'route', 'region'], true)
        );
    }

    private function tipoGenericoParaGeometria(?string $geometryType): array
    {
        return match ($geometryType) {
            'polyline' => ['dialog' => 'route', 'text' => 'Ruta', 'icon' => 'mdi-chart-timeline-variant', 'route' => 'route'],
            'polygon' => ['dialog' => 'region', 'text' => 'Región', 'icon' => 'mdi-vector-polygon', 'route' => 'regions'],
            default => self::TIPO_GENERICO_MARKER,
        };
    }

    private function normalizarTexto(string $texto): string
    {
        return trim(mb_strtolower(Str::ascii($texto)));
    }

    private function haversineMetros(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
