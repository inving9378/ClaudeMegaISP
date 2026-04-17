<?php

namespace App\Http\Controllers\Module\Maps;

use App\Http\Controllers\Controller;
use App\Repositories\MapLayerRepository;
use App\Repositories\MapProyectRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use XMLReader;
use Illuminate\Support\Str;

class KMZController extends Controller
{
    protected $projectRepository;
    protected $layerRpository;

    public function __construct()
    {
        $this->projectRepository = new MapProyectRepository();
        $this->layerRpository = new MapLayerRepository();
    }

    public function loadKMZ(Request $request, $id = null)
    {
        $request->validate([
            'file' => 'required|file'
        ]);
        $file = $request->file('file');
        $path = $file->getRealPath();
        $mimeType = $file->getMimeType();
        if (in_array($mimeType, ['application/zip', 'application/vnd.google-earth.kmz'])) {
            $kml = $this->getKML($path);
            if (!isset($kml)) {
                return response()->json(['error' => 'No se encontró archivo KML dentro del KMZ'], 400);
            }
        } elseif (in_array($mimeType, ['application/vnd.google-earth.kml+xml', 'text/xml'])) {
            $kml = file_get_contents($path);
        } else {
            return response()->json(['error' => 'Formato de archivo no soportado'], 400);
        }
        $geojson = $this->parseKmlToJson($kml);
        return $this->saveKMZ($id, $geojson);
    }

    public function getKML($path)
    {
        $kml = null;
        $zip = new \ZipArchive;
        if ($zip->open($path) === TRUE) {
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

    public function parseKmlToJson($filePath)
    {
        $reader = new XMLReader();
        $reader->xml($filePath);
        $result = [];
        while ($reader->read()) {
            if ($reader->nodeType == XMLReader::ELEMENT) {
                if ($reader->name == 'Folder' || $reader->name == 'Document') {
                    $result[] = $this->parseNode($reader);
                }
            }
        }
        $reader->close();
        return $result;
    }

    protected function parseNode($reader, $parentName = null)
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
                        $node['children'][] = $this->parseNode($reader, $node['name']);
                        break;

                    case 'Placemark':
                        $placemark = $this->parsePlacemark($reader);
                        if (isset($placemark['coords']) && isset($placemark['name']) && $placemark['name'] !== '') {
                            $node['children'][] = $placemark;
                        }
                        break;

                    case 'ExtendedData':
                        $node['extended_data'] = $this->parseExtendedData($reader);
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

    protected function parsePlacemark($reader)
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
                        $placemark = [...$placemark, ...$this->parseGeometry($reader)];
                        break;
                    case 'LineString':
                        $placemark['icon'] = 'mdi-chart-timeline-variant';
                        $placemark = [...$placemark, ...$this->parseGeometry($reader)];
                        break;
                    case 'Polygon':
                        $placemark['icon'] = 'mdi-vector-polygon';
                        $placemark = [...$placemark, ...$this->parseGeometry($reader)];
                        break;
                    case 'MultiGeometry':
                        $placemark['icon'] = 'mdi-vector-polygon';
                        $placemark = [...$placemark, ...$this->parseGeometry($reader)];
                        break;

                    case 'ExtendedData':
                        $placemark['extended_data'] = $this->parseExtendedData($reader);
                        break;

                    case 'LookAt':
                        $placemark['look_at'] = $this->parseLookAt($reader);
                        break;
                }
                $uuid = Str::uuid();
                $placemark['id'] = $uuid;
                $placemark['key'] = $uuid;
            }
        }

        return $placemark;
    }

    protected function parseGeometry($reader)
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

    protected function parseExtendedData($reader)
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

    protected function parseLookAt($reader)
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

    public function saveKMZ($id, $kmz)
    {
        set_time_limit(300);
        try {
            $nodes = DB::transaction(
                function () use ($kmz, $id) {
                    $nodes = [];
                    foreach ($kmz as &$node) {
                        $node['classification'] = 'project';
                        $node['parent_id'] = $id;
                        $project = $this->projectRepository->create($node);
                        $nodes[] = 'project-' . $project->id;
                        $this->saveNode($project->id, $node);
                    }
                    return $nodes;
                }
            );
            return response()->json([
                'success' => true,
                'tickeds' => $nodes,
                'nodes' => $this->projectRepository->getNodes()
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ]);
        }
    }

    public function saveNode($id, $node)
    {
        $nodes = collect($node['children']);
        $nodes->where('is_layer', true)
            ->map(fn($l) => $this->normalizeLayer($id, $l))
            ->whenNotEmpty(
                fn($collection) =>
                $this->layerRpository->getModel()->insert($collection->all())
            );
        foreach ($node['children'] as &$n) {
            if (!$n['is_layer']) {
                $n['classification'] = 'project';
                $n['parent_id'] = $id;
                $project = $this->projectRepository->create($n);
                $this->saveNode($project->id, $n);
            }
        }
    }

    public function saveLayersFromNode($id, $node)
    {
        collect($node['children'])
            ->where('is_layer', true)
            ->map(fn($l) => $this->normalizeLayer($id, $l))
            ->whenNotEmpty(
                fn($collection) =>
                $this->layerRpository->getModel()->insert($collection->all())
            );
        return true;
    }

    public function normalizeNode($id, $n)
    {
        return [
            'classification' => 'project',
            'name' => $n['name'],
            'created_by' => auth()->user()->id,
            'updated_by' => auth()->user()->id,
            'parent_id' => $id,
            'created_at' => now(),
            'updated_at' => now()
        ];
    }

    public function normalizeLayer($id, $n)
    {
        return [
            ...$this->getTypeConfig($n['type']),
            'coords' => json_encode($n['coords']),
            'data' => json_encode($n['data'] ?? null),
            'color' => '#6666ff',
            'classification' => 'project',
            'label' => 'name',
            'icon_color' => '#FFFFFF',
            'project_id' => $id,
            'type' => $n['type'],
            'created_at' => now(),
            'updated_at' => now()
        ];
    }

    private function getTypeConfig(string $type): array
    {
        return match ($type) {
            'polyline' => [
                'route' => 'route',
                'dialog' => 'route',
                'text' => 'Ruta',
                'icon' => 'mdi-chart-timeline-variant'
            ],
            'polygon' => [
                'route' => 'regions',
                'dialog' => 'region',
                'text' => 'Región',
                'icon' => 'mdi-vector-polygon'
            ],
            default => [
                'route' => 'kmz',
                'dialog' => 'kmz',
                'text' => 'Objeto KMZ',
                'icon' => 'mdi-map-marker'
            ]
        };
    }
}
