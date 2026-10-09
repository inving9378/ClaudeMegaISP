<?php

namespace App\Modules\Addons\MapaRed\Services\Export;

use App\Modules\Addons\MapaRed\Models\MapaRedProyect;

/**
 * MR-25 Fase 4 (item roadmap #9990445) — FeatureCollection GeoJSON de las layers de un
 * proyecto. GeoJSON usa [lon, lat] (mismo orden invertido que KML).
 */
class GeoJsonExportService
{
    public function exportar(MapaRedProyect $proyecto): array
    {
        $features = $proyecto->layers()->get()
            ->map(fn ($layer) => $this->feature($layer))
            ->filter()
            ->values();

        return [
            'type' => 'FeatureCollection',
            'name' => $proyecto->name,
            'features' => $features,
        ];
    }

    private function feature($layer): ?array
    {
        $geometria = match ($layer->type) {
            'marker' => $this->point($layer->coords),
            'polyline' => $this->lineString($layer->coords),
            'polygon' => $this->polygon($layer->coords),
            default => null,
        };

        if ($geometria === null) {
            return null;
        }

        return [
            'type' => 'Feature',
            'properties' => [
                'id' => $layer->id,
                'name' => $layer->text,
                'label' => $layer->label,
                'classification' => $layer->classification,
                'dialog' => $layer->dialog,
            ],
            'geometry' => $geometria,
        ];
    }

    private function point(?array $coords): ?array
    {
        if (!isset($coords['lat'], $coords['lng'])) {
            return null;
        }

        return ['type' => 'Point', 'coordinates' => [$coords['lng'], $coords['lat']]];
    }

    private function lineString(?array $coords): ?array
    {
        if (!is_array($coords) || $coords === []) {
            return null;
        }

        return ['type' => 'LineString', 'coordinates' => $this->tuplas($coords)];
    }

    private function polygon(?array $coords): ?array
    {
        if (!is_array($coords) || $coords === []) {
            return null;
        }

        $anillo = $this->tuplas($coords);
        if ($anillo[0] !== end($anillo)) {
            $anillo[] = $anillo[0];
        }

        return ['type' => 'Polygon', 'coordinates' => [$anillo]];
    }

    private function tuplas(array $coords): array
    {
        return array_map(fn ($p) => [$p['lng'] ?? 0, $p['lat'] ?? 0], $coords);
    }
}
