<?php

namespace App\Modules\Addons\MapaRed\Repositories;

use App\Models\ClientMainInformation;
use App\Modules\Addons\MapaRed\Models\MapaRedLayer;
use App\Modules\Addons\MapaRed\Models\MapaRedProyect;
use App\Repositories\BaseRepository;
use Illuminate\Support\Facades\DB;

/**
 * Espejo de App\Repositories\MapProyectRepository, apuntando a MapaRedProyect/MapaRedLayer
 * (MR-06a-4, item #9990336). Solo porta los métodos que consume LayersController — el resto
 * (JSTHREE, box/rack/etc.) pertenece al grupo "Mapas/infra" ya confirmado muerto (ver
 * docs/mapared-mr06-checklist-paridad-item-942.md §0), no se porta.
 */
class MapaRedProyectRepository extends BaseRepository
{
    public function getModel(): MapaRedProyect
    {
        return new MapaRedProyect();
    }

    public function getNodes()
    {
        $nodes = $this->getDefaultNodes();
        $allNodes = $this->getProjectsTree();
        foreach ($allNodes as $node) {
            $is_layer = (bool)$node->is_layer;
            if (!$is_layer) {
                $nodes[] = $this->getNodeData($node, 'project');
                $nodes[] = $this->getNodeData($node, 'network');
            } else {
                $nodes[] = $this->getNodeData($node, $node->classification);
            }
        }
        $clients = ClientMainInformation::whereNotNull('geodata')->get();
        foreach ($clients as $client) {
            $nodes[] = $this->getClientNodeData($client);
        }
        return $nodes;
    }

    public function getDataFromObject($node)
    {
        return $node instanceof MapaRedProyect ? $this->getProjectData($node) : $this->getLayerData($node);
    }

    public function getLayersFromIds($ids)
    {
        $layers = MapaRedLayer::whereIn('id', $ids)->get();
        $nodes = [];
        foreach ($layers as $node) {
            $nodes[] = $this->getLayerData($node);
        }
        return $nodes;
    }

    public function getProjectData($node)
    {
        return [
            'id' => $node->id,
            'key' => sprintf('project-%d', $node->id),
            'parent_key' => sprintf('project-%s', $node->parent_id ?? 'root'),
            'name' => $node->name,
            'text' => $node->name,
            'level' => $node->level,
            'text_node' => $node->name,
            'classification' => 'project',
            'parent_id' => $node->parent_id,
            'is_layer' => false,
            'icon' => 'mdi-folder-outline',
            'icon_color' => null,
            'dialog' => 'folder',
            'coords' => null,
            'data' => null,
            'type' => null,
            'color' => null,
        ];
    }

    public function getLayerData($node)
    {
        return [
            'id' => $node->id,
            'key' => sprintf('layer-%d', $node->id),
            'parent_key' => sprintf('%s-%s', $node->classification, $node->project_id ?? 'root'),
            'name' => $node->data[$node->label],
            'text' => $node->text,
            'level' => $node->level,
            'text_node' => $node->data[$node->label],
            'classification' => $node->classification,
            'parent_id' => $node->project_id,
            'is_layer' => true,
            'icon' => $node->icon,
            'icon_color' => $node->icon_color,
            'weight' => $node->weight,
            'distance' => $node->distance,
            'dialog' => $node->dialog,
            'coords' => $node->coords,
            'properties' => $node->data,
            'data' => $node->data,
            'type' => $node->type,
            'color' => $node->color,
            'layers' => $node->layers,
        ];
    }

    public function getDefaultNodes()
    {
        $nodes = [
            [
                'id' => null,
                'key' => 'root-node',
                'parent_key' => null,
                'name' => 'Meganet',
                'text_node' => 'Meganet',
                'classification' => null,
                'parent_id' => null,
                'is_layer' => false,
                'icon' => 'mdi-folder-outline',
            ],
            [
                'id' => null,
                'key' => 'network-root',
                'parent_key' => 'root-node',
                'name' => 'Red',
                'text_node' => 'Red',
                'classification' => null,
                'parent_id' => null,
                'is_layer' => false,
                'icon' => 'mdi-folder-outline',
            ],
            [
                'id' => null,
                'key' => 'project-root',
                'parent_key' => 'root-node',
                'name' => 'Proyectos',
                'text_node' => 'Proyectos',
                'classification' => 'project',
                'parent_id' => null,
                'is_layer' => false,
                'icon' => 'mdi-folder-outline',
            ],
            [
                'id' => null,
                'key' => 'client-root',
                'parent_key' => 'root-node',
                'name' => 'Clientes',
                'text_node' => 'Clientes',
                'classification' => null,
                'parent_id' => null,
                'is_layer' => false,
                'icon' => 'mdi-folder-outline',
                'text' => 'Cliente',
            ],
        ];
        $states = ClientMainInformation::whereNotNull('estado')->distinct()->pluck('estado');
        foreach ($states as $s) {
            $nodes[] = [
                'id' => $s,
                'key' => sprintf('client-%s', $s),
                'parent_key' => 'client-root',
                'text' => 'Cliente',
                'name' => $s,
                'icon' => 'mdi-folder-outline',
                'text_node' => $s,
                'classification' => null,
                'parent_id' => null,
            ];
        }
        return $nodes;
    }

    public function getNodeData($node, $classification = 'project')
    {
        $is_layer = (bool)$node->is_layer;
        return [
            'id' => $node->id,
            'key' => sprintf('%s-%d', $is_layer ? 'layer' : $classification, $node->id),
            'parent_key' => sprintf('%s-%s', $classification, $node->parent_id ?? 'root'),
            'name' => $node->name,
            'text' => $node->text,
            'level' => $node->level,
            'text_node' => $node->name,
            'classification' => $classification,
            'parent_id' => $node->parent_id,
            'is_layer' => $is_layer,
            'icon' => $node->icon,
            'icon_color' => $node->icon_color,
            'weight' => $node->weight,
            'distance' => $node->distance,
            'dialog' => $node->dialog ?? 'folder',
            'coords' => json_decode($node->coords) ?? null,
            'data' => json_decode($node->data) ?? null,
            'properties' => json_decode($node->data) ?? null,
            'type' => $node->type,
            'color' => $node->color,
            'layers' => json_decode($node->layers) ?? null,
        ];
    }

    public function getClientNodeData($item)
    {
        $coords = explode(',', $item->geodata, 2);
        $coords = [
            'lat' => (float)trim($coords[0]),
            'lng' => (float)trim($coords[1]),
        ];
        $color = '#5bc0de';
        $state = $item->estado;
        if ($state == 'Activo') {
            $color = '#5cb85c';
        } elseif ($state == 'Bloqueado') {
            $color = '#b52b2b';
        } elseif ($state == 'Cancelado') {
            $color = '#808080';
        } elseif ($state == 'Inactivo') {
            $color = '#f0ad4e';
        }
        return [
            'id' => $item->id,
            'key' => 'client-' . $item->id,
            'parent_key' => sprintf('client-%s', $state),
            'text' => 'Cliente',
            'coords' => $coords,
            'name' => $item->client_name_with_fathers_names,
            'text_node' => $item->client_name_with_fathers_names,
            'icon' => 'mdi-account',
            'icon_color' => '#FFFFFF',
            'color' => $color,
            'dialog' => 'client',
            'type' => 'marker',
            'is_layer' => true,
            'classification' => 'client',
            'properties' => [
                'id' => $item->id,
                'client_id' => $item->client_id,
                'name' => $item->client_name_with_fathers_names,
            ],
        ];
    }

    public function getProjectsTree()
    {
        return DB::select("with children AS (SELECT id, parent_id, name, classification, FALSE AS is_layer, 'mdi-folder-outline' AS icon, NULL AS icon_color, NULL AS color, NULL AS dialog, NULL AS coords, NULL AS data, NULL AS weight, NULL AS distance, NULL AS type, NULL AS text, level, NULL AS layers FROM mapared_proyects
            UNION all
            SELECT l.id, l.project_id AS parent_id, JSON_UNQUOTE(JSON_EXTRACT(DATA, CONCAT('$.',label))) AS name, l.classification, TRUE AS is_layer, l.icon, l.icon_color, l.color, l.dialog, l.coords, l.data, l.weight, l.distance, l.type, l.text, l.level, CASE WHEN COUNT(r.id) = 0 THEN NULL ELSE JSON_ARRAYAGG(r.route_id) END AS layers FROM mapared_layers l LEFT JOIN mapared_layers_routes r ON l.id=r.layer_id GROUP BY l.id)
            SELECT * FROM children ORDER BY parent_id, level");
    }
}
