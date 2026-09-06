<?php

namespace App\Modules\Addons\MapaRed\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MapaRed\Repositories\MapaRedLayerRepository;
use App\Modules\Addons\MapaRed\Repositories\MapaRedProyectRepository;
use App\Modules\Addons\MapaRed\Services\KmlParserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Espejo de App\Modules\Addons\Mapas\Controllers\Geo\KMZController, apuntando a
 * los modelos mapared_* (MR-06a-3, item #9990335). Ver docs/mapared-mr06-checklist-paridad-item-942.md
 * sección 1.
 */
class KMZController extends Controller
{
    protected $projectRepository;
    protected $layerRpository;

    public function __construct()
    {
        $this->projectRepository = new MapaRedProyectRepository();
        $this->layerRpository = new MapaRedLayerRepository();
    }

    public function loadKMZ(Request $request, $id = null)
    {
        $request->validate([
            'file' => 'required|file'
        ]);
        $file = $request->file('file');
        $path = $file->getRealPath();
        $mimeType = $file->getMimeType();
        try {
            $kml = KmlParserService::extraerKmlDeArchivo($path, $mimeType);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
        $geojson = $this->parseKmlToJson($kml);
        return $this->saveKMZ($id, $geojson);
    }

    /**
     * @deprecated usar KmlParserService::getKML() — se conserva como delegador delgado
     * porque es público y podría tener consumidores fuera de este controller.
     */
    public function getKML($path)
    {
        return KmlParserService::getKML($path);
    }

    /**
     * @deprecated usar KmlParserService::parseKmlToJson() — se conserva como delegador
     * delgado porque es público y podría tener consumidores fuera de este controller.
     */
    public function parseKmlToJson($filePath)
    {
        return KmlParserService::parseKmlToJson($filePath);
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
            'created_by' => auth()->user()?->id,
            'updated_by' => auth()->user()?->id,
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
