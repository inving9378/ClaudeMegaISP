<?php

namespace App\Modules\Addons\MapaRed\Services;

use Illuminate\Support\Str;
use XMLReader;

/**
 * MR-25 (item #961) — parseo de KML/KMZ extraído de `KMZController` (comportamiento 1:1,
 * solo movido para poder reusarlo desde el importador nuevo sin duplicar ~150 líneas de
 * XMLReader). `KMZController` sigue siendo el consumidor del flujo viejo (árbol completo →
 * `mapared_proyects`); este servicio es la fuente única del parseo KML/KMZ→árbol.
 */
class KmlParserService
{
    /**
     * Extrae el string KML crudo de un archivo subido (KMZ zip o KML plano), replicando
     * la detección de mime original de KMZController::loadKMZ.
     */
    public static function extraerKmlDeArchivo(string $path, ?string $mimeType): string
    {
        if (in_array($mimeType, ['application/zip', 'application/vnd.google-earth.kmz'])) {
            $kml = self::getKML($path);
            if (!isset($kml)) {
                throw new \InvalidArgumentException('No se encontró archivo KML dentro del KMZ');
            }
            return $kml;
        }

        if (in_array($mimeType, ['application/vnd.google-earth.kml+xml', 'text/xml'])) {
            return file_get_contents($path);
        }

        throw new \InvalidArgumentException('Formato de archivo no soportado');
    }

    public static function getKML($path)
    {
        $kml = null;
        $zip = new \ZipArchive;
        if ($zip->open($path) === true) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                if (pathinfo($filename, PATHINFO_EXTENSION) == 'kml') {
                    $kml = $zip->getFromIndex($i);
                    break;
                }
            }
            $zip->close();
        }
        return $kml;
    }

    public static function parseKmlToJson($filePath)
    {
        $reader = new XMLReader();
        $reader->xml($filePath);
        $result = [];
        while ($reader->read()) {
            if ($reader->nodeType == XMLReader::ELEMENT) {
                if ($reader->name == 'Folder' || $reader->name == 'Document') {
                    $result[] = self::parseNode($reader);
                }
            }
        }
        $reader->close();
        return $result;
    }

    /**
     * Recorre el árbol devuelto por `parseKmlToJson` y devuelve una lista PLANA de
     * placemarks (is_layer=true), cada uno con su `folder_path` (nombres de las carpetas
     * ancestro, en orden) — la señal de contexto que el detector de tipo de MR-25 usa junto
     * al nombre del placemark (ej. carpeta "NAPs" + nombre "NAP-01").
     */
    public static function aplanar(array $tree, array $folderPath = []): array
    {
        $placemarks = [];
        foreach ($tree as $node) {
            $currentPath = $node['is_layer'] ? $folderPath : [...$folderPath, $node['name'] ?? ''];
            foreach ($node['children'] ?? [] as $child) {
                if ($child['is_layer']) {
                    $placemarks[] = [...$child, 'folder_path' => $currentPath];
                } else {
                    $placemarks = [...$placemarks, ...self::aplanar([$child], $currentPath)];
                }
            }
        }
        return $placemarks;
    }

    protected static function parseNode($reader, $parentName = null)
    {
        $node = [
            'id' => null,
            'key' => null,
            'parent_key' => null,
            'name' => null,
            'icon' => 'mdi-folder-outline',
            'text_node' => null,
            'classification' => 'kmz',
            'parent_id' => null,
            'description' => null,
            'children' => [],
            'extended_data' => [],
            'text' => 'Objeto KMZ',
            'is_layer' => false
        ];

        $currentName = $reader->name;

        if ($reader->hasAttributes) {
            while ($reader->moveToNextAttribute()) {
                if ($reader->name == 'id') {
                    $node['id'] = $reader->value;
                }
            }
            $reader->moveToElement();
        }

        $depth = $reader->depth;

        while ($reader->read() && !($reader->nodeType == XMLReader::END_ELEMENT && $reader->name == $currentName && $reader->depth == $depth)) {
            if ($reader->nodeType == XMLReader::ELEMENT) {
                switch ($reader->name) {
                    case 'name':
                        $reader->read();
                        $node['name'] = $reader->value;
                        $node['text_node'] = $reader->value;
                        break;

                    case 'description':
                        $reader->read();
                        $node['description'] = $reader->value;
                        break;

                    case 'Folder':
                    case 'Document':
                        $node['children'][] = self::parseNode($reader, $node['name']);
                        break;

                    case 'Placemark':
                        $placemark = self::parsePlacemark($reader);
                        if (isset($placemark['coords']) && isset($placemark['name']) && $placemark['name'] !== '') {
                            $node['children'][] = $placemark;
                        }
                        break;

                    case 'ExtendedData':
                        $node['extended_data'] = self::parseExtendedData($reader);
                        break;
                }
                $uuid = Str::uuid();
                $node['id'] = sprintf('kmz-%s', $uuid);
                $node['key'] = sprintf('kmz-%s', $uuid);
            }
        }
        if (!isset($node['name'])) {
            $node['name'] = 'Objetos KMZ';
        }
        return $node;
    }

    protected static function parsePlacemark($reader)
    {
        $placemark = [
            'id' => null,
            'key' => null,
            'parent_key' => null,
            'name' => '',
            'icon' => null,
            'text_node' => null,
            'classification' => 'kmz',
            'parent_id' => null,
            'children' => [],
            'extended_data' => [],
            'look_at' => null,
            'data' => null,
            'properties' => null,
            'color' => '#5bc0de',
            'text' => 'Objeto KMZ',
            'is_layer' => true
        ];

        $depth = $reader->depth;

        while ($reader->read() && !($reader->nodeType == XMLReader::END_ELEMENT && $reader->name == 'Placemark' && $reader->depth == $depth)) {
            if ($reader->nodeType == XMLReader::ELEMENT) {
                switch ($reader->name) {
                    case 'name':
                        $reader->read();
                        $placemark['name'] = $reader->value;
                        $placemark['text_node'] = $reader->value;
                        $placemark['data']['name'] = $reader->value;
                        $placemark['properties']['name'] = $reader->value;
                        break;

                    case 'description':
                        $reader->read();
                        $placemark['data']['description'] = $reader->value;
                        $placemark['properties']['description'] = $reader->value;
                        break;

                    case 'Point':
                        $placemark['icon'] = 'mdi-map-marker';
                        $placemark = [...$placemark, ...self::parseGeometry($reader)];
                        break;
                    case 'LineString':
                        $placemark['icon'] = 'mdi-chart-timeline-variant';
                        $placemark = [...$placemark, ...self::parseGeometry($reader)];
                        break;
                    case 'Polygon':
                        $placemark['icon'] = 'mdi-vector-polygon';
                        $placemark = [...$placemark, ...self::parseGeometry($reader)];
                        break;
                    case 'MultiGeometry':
                        $placemark['icon'] = 'mdi-vector-polygon';
                        $placemark = [...$placemark, ...self::parseGeometry($reader)];
                        break;

                    case 'ExtendedData':
                        $placemark['extended_data'] = self::parseExtendedData($reader);
                        break;

                    case 'LookAt':
                        $placemark['look_at'] = self::parseLookAt($reader);
                        break;
                }
                $uuid = Str::uuid();
                $placemark['id'] = $uuid;
                $placemark['key'] = $uuid;
            }
        }

        return $placemark;
    }

    protected static function parseGeometry($reader)
    {
        $geometry = [
            'type' => $reader->name,
            'coords' => null,
        ];

        $depth = $reader->depth;

        while ($reader->read() && !($reader->nodeType == XMLReader::END_ELEMENT && $reader->name == $geometry['type'] && $reader->depth == $depth)) {
            if ($reader->nodeType == XMLReader::ELEMENT) {
                switch ($reader->name) {
                    case 'coordinates':
                        $reader->read();
                        $coords = trim($reader->value);
                        $coordSet = explode(' ', $coords);
                        $coords = [];
                        foreach ($coordSet as $c) {
                            if (!empty($c)) {
                                $parts = explode(',', trim($c));
                                if (count($parts) >= 2) {
                                    $coords[] = [
                                        'lat' => (float)$parts[1],
                                        'lng' => (float)$parts[0]
                                    ];
                                }
                            }
                        }
                        $geometry['coords'] = count($coords) > 1 ? $coords : $coords[0] ?? null;
                        break;
                }
            }
        }

        $normalizedGeometry = [
            'LineString' => 'polyline',
            'Point' => 'marker',
            'Polygon' => 'polygon'
        ];

        $geometry['type'] = $normalizedGeometry[$geometry['type']];

        return $geometry;
    }

    protected static function parseExtendedData($reader)
    {
        $data = [];
        $depth = $reader->depth;

        while ($reader->read() && !($reader->nodeType == XMLReader::END_ELEMENT && $reader->name == 'ExtendedData' && $reader->depth == $depth)) {
            if ($reader->nodeType == XMLReader::ELEMENT && $reader->name == 'Data') {
                $name = $reader->getAttribute('name');
                if ($reader->read() && $reader->name == 'value') {
                    $reader->read();
                    $data[$name] = $reader->value;
                }
            }
        }

        return $data;
    }

    protected static function parseLookAt($reader)
    {
        $lookAt = [];
        $depth = $reader->depth;

        while ($reader->read() && !($reader->nodeType == XMLReader::END_ELEMENT && $reader->name == 'LookAt' && $reader->depth == $depth)) {
            if ($reader->nodeType == XMLReader::ELEMENT) {
                $elementName = $reader->name;
                $reader->read();
                if ($reader->nodeType == XMLReader::TEXT) {
                    $lookAt[$elementName] = $reader->value;
                }
            }
        }

        return $lookAt;
    }
}
