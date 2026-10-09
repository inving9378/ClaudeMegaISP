<?php

namespace App\Modules\Addons\MapaRed\Services\Export;

use App\Modules\Addons\MapaRed\Models\MapaRedProyect;

/**
 * MR-25 Fase 4 (item roadmap #9990445) — espejo inverso de KmlParserService: convierte las
 * layers de un proyecto de vuelta a KML descargable. Mismo mapeo de tipos que el importador
 * (KmlParserService::mapType): marker->Point, polyline->LineString, polygon->Polygon.
 */
class KmlExportService
{
    public function exportar(MapaRedProyect $proyecto): string
    {
        $layers = $proyecto->layers()->get();

        $placemarks = $layers->map(fn ($layer) => $this->placemark($layer))->filter()->implode('');

        $nombre = $this->escapar($proyecto->name);

        return <<<KML
<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
<Document>
<name>{$nombre}</name>
{$placemarks}
</Document>
</kml>
KML;
    }

    private function placemark($layer): ?string
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

        $nombre = $this->escapar($layer->text ?: $layer->label ?: class_basename($layer->dialog ?? 'elemento'));

        return "<Placemark><name>{$nombre}</name>{$geometria}</Placemark>";
    }

    private function point(?array $coords): ?string
    {
        if (!isset($coords['lat'], $coords['lng'])) {
            return null;
        }

        return '<Point><coordinates>' . $this->coordTupla($coords) . '</coordinates></Point>';
    }

    private function lineString(?array $coords): ?string
    {
        if (!is_array($coords) || $coords === []) {
            return null;
        }

        $puntos = collect($coords)->map(fn ($p) => $this->coordTupla($p))->implode(' ');

        return "<LineString><coordinates>{$puntos}</coordinates></LineString>";
    }

    private function polygon(?array $coords): ?string
    {
        if (!is_array($coords) || $coords === []) {
            return null;
        }

        $puntos = collect($coords)->map(fn ($p) => $this->coordTupla($p))->implode(' ');
        // KML exige el anillo cerrado (primer punto == último).
        $primero = $this->coordTupla($coords[0]);
        if (!str_ends_with($puntos, $primero)) {
            $puntos .= " {$primero}";
        }

        return "<Polygon><outerBoundaryIs><LinearRing><coordinates>{$puntos}</coordinates></LinearRing></outerBoundaryIs></Polygon>";
    }

    /** KML usa lon,lat (orden invertido respecto a como se guarda aquí, lat/lng). */
    private function coordTupla(array $p): string
    {
        return ($p['lng'] ?? 0) . ',' . ($p['lat'] ?? 0) . ',0';
    }

    private function escapar(?string $texto): string
    {
        return htmlspecialchars($texto ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
