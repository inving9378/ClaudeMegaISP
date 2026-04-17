<?php

namespace App\Repositories;

use App\Models\Box;
use App\Models\BoxInput;
use App\Models\ClientMainInformation;
use App\Models\MapLayer;
use App\Models\MapProyect;
use App\Models\Point;
use App\Models\Pole;
use App\Models\Site;
use App\Models\Trench;
use App\Repositories\BaseRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Francisco López Zetina
 */
class MapProyectRepository extends BaseRepository
{
    public function getModel(): MapProyect
    {
        return new MapProyect();
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
        return $node instanceof MapProyect ? $this->getProjectData($node) : $this->getLayerData($node);
    }

    public function getLayersFromIds($ids)
    {
        $layers = MapLayer::whereIn('id', $ids)->get();
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
            'layers' => $node->layers
        ];
    }

    public function getDefaultNodes()
    {
        $nodes = [
            [
                'id' => null,
                'key' => "root-node",
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
            ]
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
                'parent_id' => null
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
            'layers' => json_decode($node->layers) ?? null
        ];
    }

    public function getClientNodeData($item)
    {
        $coords = explode(',', $item->geodata, 2);
        $coords = [
            'lat' => (float)trim($coords[0]),
            'lng' => (float)trim($coords[1])
        ];
        $color = '#5bc0de';
        $state = $item->estado;
        if ($state == 'Activo') {
            $color = '#5cb85c';
        } else if ($state == 'Bloqueado') {
            $color = '#b52b2b';
        } else if ($state == 'Cancelado') {
            $color = '#808080';
        } else if ($state == 'Inactivo') {
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
                'name' => $item->client_name_with_fathers_names
            ]
        ];
    }

    public function getProjectsTree()
    {
        return DB::select("with children AS (SELECT id, parent_id, name, classification, FALSE AS is_layer, 'mdi-folder-outline' AS icon, NULL AS icon_color, NULL AS color, NULL AS dialog, NULL AS coords, NULL AS data, NULL AS weight, NULL AS distance, NULL AS type, NULL AS text, level, NULL AS layers FROM map_proyects
            UNION all
            SELECT l.id, l.project_id AS parent_id, JSON_UNQUOTE(JSON_EXTRACT(DATA, CONCAT('$.',label))) AS name, l.classification, TRUE AS is_layer, l.icon, l.icon_color, l.color, l.dialog, l.coords, l.data, l.weight, l.distance, l.type, l.text, l.level, CASE WHEN COUNT(r.id) = 0 THEN NULL ELSE JSON_ARRAYAGG(r.route_id) END AS layers FROM map_layers l LEFT JOIN map_layers_routes r ON l.id=r.layer_id GROUP BY l.id)
            SELECT * FROM children ORDER BY parent_id, level");
    }

    public function getOrCreate(string $name): MapProyect
    {
        $object = $this->getByName($name);

        if (!empty($object))
            return $object;

        return $this->create([
            "name" => $name
        ]);
    }

    /**
     * @param string $text
     * @return LengthAwarePaginator
     */
    public function getListToSelect($text, int $page): LengthAwarePaginator
    {
        $querry = $this->getModel()
            ->select(
                "map_proyects.id",
                "map_proyects.name AS text"
            )
            ->name($text)
            ->orderBy('map_proyects.id')
            ->groupBy(
                "map_proyects.id",
                "map_proyects.name"
            );

        return $querry->paginate(7, $page);
    }

    /*
        |----------------------------------------------------------------------------
        |  JSTHREE
        |----------------------------------------------------------------------------
        */
    public function getMapPoints(int $mapProyectId)
    {
        $parentTable = DB::table($this->getModel()->getTable())
            ->select(
                DB::raw('CONCAT(map_routes.id, "-", "route") AS id'),
                DB::raw('"#" AS parent'),
                'map_routes.name AS text',
                DB::raw('"bi bi-bezier2" AS icon')
            )
            ->leftJoin("map_routes", "map_routes.map_proyect_id", "map_proyects.id")
            ->where("map_proyects.id", $mapProyectId)
            ->whereNotNull("map_routes.id")
            ->get();

        $completeData = $this->getObjectsToTree($parentTable, $mapProyectId);
        $completeData = $this->getSiteChilds($completeData, $mapProyectId);
        $completeData = $this->getBoxObjectsToTree($completeData, $mapProyectId);

        return $completeData;
    }

    /**
     * consults to get objects to send to jstree
     * @param string $text
     * @return LengthAwarePaginator
     */
    public function getObjectsToTree($parentTable, $mapProyectId)
    {
        $objects = DB::table('tables')->where('has_position', 1)->get();

        foreach ($objects as $object) {
            if ($object->model_class === Box::class)
                continue;

            $childInputTable = DB::table($object->name . ' AS object')
                ->select(
                    DB::raw('CONCAT(object.id, "-", "' . $object->type . '" ) AS id'),
                    DB::raw($this->getOptionTree($object->model_class, 'parent')),
                    DB::raw('object.' . $object->search_column_name . ' AS text'),
                    DB::raw($this->getOptionTree($object->model_class, 'icon')),
                )
                ->join('map_links', function ($join) use ($object) {
                    $join->on('map_links.input_id', '=', 'object.id')->where('map_links.input_type', '=', $object->model_class);
                })
                ->join("map_routes", "map_routes.id", "map_links.map_route_id")
                ->where("object.map_proyect_id", $mapProyectId)
                ->get();

            $childOutputTable = DB::table($object->name . ' AS object')
                ->select(
                    DB::raw('CONCAT(object.id, "-", "' . $object->type . '"  ) AS id'),
                    DB::raw($this->getOptionTree($object->model_class, 'parent')),
                    DB::raw('object.' . $object->search_column_name . ' AS text'),
                    DB::raw($this->getOptionTree($object->model_class, 'icon')),
                )
                ->join('map_links', function ($join) use ($object) {
                    $join->on('map_links.output_id', '=', 'object.id')->where('map_links.output_type', '=', $object->model_class);
                })
                ->join("map_routes", "map_routes.id", "map_links.map_route_id")
                ->where("object.map_proyect_id", $mapProyectId)
                ->get();

            $freePointsTable = DB::table($object->name . ' AS object')
                ->select(
                    DB::raw('CONCAT(object.id, "-", "' . $object->type . '"  ) AS id'),
                    DB::raw('"#" AS parent'),
                    DB::raw('object.' . $object->search_column_name . ' AS text'),
                    DB::raw($this->getOptionTree($object->model_class, 'icon')),
                )
                ->leftJoin('map_links AS outputLinks', function ($join) use ($object) {
                    $join->on('outputLinks.output_id', '=', 'object.id')->where('outputLinks.output_type', '=', $object->model_class);
                })
                ->leftJoin('map_links AS inputLinks', function ($join) use ($object) {
                    $join->on('inputLinks.input_id', '=', 'object.id')->where('inputLinks.input_type', '=', $object->model_class);
                })
                ->where("object.map_proyect_id", $mapProyectId)
                ->whereNull('inputLinks.map_route_id')
                ->whereNull('outputLinks.map_route_id')
                ->get();

            $completeData = $childOutputTable->merge($childInputTable)->unique();
            $completeData = $completeData->merge($freePointsTable);
            $parentTable = $parentTable->merge($completeData);
        }

        return $parentTable;
    }

    public function getBoxObjectsToTree($parentTable, $mapProyectId)
    {
        $childInputTable = DB::table('boxes AS object')
            ->select(
                DB::raw('CONCAT(object.id, "-", "box") AS id'),
                DB::raw($this->getOptionTree(Box::class, 'parent')),
                DB::raw('object.nomenclature AS text'),
                DB::raw($this->getOptionTree(Box::class, 'boxIcon'))
            )
            ->join("box_inputs", "box_inputs.box_id", "object.id")
            ->join('map_links', function ($join) {
                $join->on('map_links.input_id', '=', 'box_inputs.id')->where('map_links.input_type', '=', BoxInput::class);
            })
            ->join("map_routes", "map_routes.id", "map_links.map_route_id")
            ->where("object.map_proyect_id", $mapProyectId)
            ->get();

        $childOutputTable = DB::table('boxes AS object')
            ->select(
                DB::raw('CONCAT(object.id, "-", "box") AS id'),
                DB::raw($this->getOptionTree(Box::class, 'parent')),
                DB::raw('object.nomenclature AS text'),
                DB::raw($this->getOptionTree(Box::class, 'boxIcon'))
            )
            ->join("box_inputs", "box_inputs.box_id", "object.id")
            ->join('map_links', function ($join) {
                $join->on('map_links.input_id', '=', 'box_inputs.id')->where('map_links.input_type', '=', BoxInput::class);
            })
            ->join("map_routes", "map_routes.id", "map_links.map_route_id")
            ->where("object.map_proyect_id", $mapProyectId)
            ->get();

        $freePointsTable = DB::table('boxes AS object')
            ->select(
                DB::raw('CONCAT(object.id, "-", "box") AS id'),
                DB::raw('"#" AS parent'),
                DB::raw('object.nomenclature AS text'),
                DB::raw($this->getOptionTree(Box::class, 'boxIcon'))
            )
            ->join("box_inputs", "box_inputs.box_id", "object.id")
            ->leftJoin('map_links AS outputLinks', function ($join) {
                $join->on('outputLinks.output_id', '=', 'box_inputs.id')->where('outputLinks.output_type', '=', BoxInput::class);
            })
            ->leftJoin('map_links AS inputLinks', function ($join) {
                $join->on('inputLinks.input_id', '=', 'box_inputs.id')->where('inputLinks.input_type', '=', BoxInput::class);
            })
            ->where("object.map_proyect_id", $mapProyectId)
            ->whereNull('inputLinks.map_route_id')
            ->whereNull('outputLinks.map_route_id')
            ->get();

        $completeData = $childOutputTable->merge($childInputTable)->unique();
        $completeData = $completeData->merge($freePointsTable)->unique();
        return $parentTable->merge($completeData);
    }

    public function getSiteChilds($parentTable, $mapProyectId)
    {

        $siteChildTypes = [
            ['racks', 'rack', 'name', 'site_id', 'sites', 'site', 'bi bi-geo'],
            ['active_equipments', 'active_equipment', 'name', 'rack_id', 'racks', 'rack', 'bi bi-geo'],
            ['passive_equipments', 'passive_equipment', 'name', 'rack_id', 'racks', 'rack', 'bi bi-geo'],
            ['cards', 'card', 'name', 'active_equipment_id', 'active_equipments', 'active_equipment', 'bi bi-geo'],
            ['transceivers', 'transceiver', 'description', 'card_id', 'cards', 'card', 'bi bi-geo'],
        ];

        foreach ($siteChildTypes as $childType) {
            $siteChilds = DB::table($childType[0] . ' AS object')
                ->select(
                    DB::raw('CONCAT(object.id, "-", "' . $childType[1] . '") AS id'),
                    DB::raw(
                        "
                            CASE
                                WHEN (rootObject.id is not null) THEN
                                    CONCAT(rootObject.id, '-', '" . $childType[5] . "')
                                ELSE
                                    '#'
                            END AS parent"
                    ),
                    DB::raw('object.' . $childType[2] . ' AS text'),
                    DB::raw('"' . $childType[6] . '" AS icon')
                )
                ->leftJoin($childType[4] . ' AS rootObject', "rootObject.id", "object." . $childType[3])
                ->where("object.map_proyect_id", $mapProyectId)
                ->get();

            $parentTable = $parentTable->merge($siteChilds);
        }

        return $parentTable;
    }

    public function getOptionTree($class, $option)
    {
        if ($option === 'icon')
            return "CASE ('" . $class . "')
                                WHEN ('" . Site::class . "') THEN
                                    'images/maps_icons/buildings-fill.svg'
                                WHEN ('" . Point::class . "') THEN
                                    'images/maps_icons/circle-fill.svg'
                                WHEN ( '" . Trench::class . "') THEN
                                    'images/maps_icons/archive-fill.svg'
                                WHEN ('" . Pole::class . "') THEN
                                    (CASE (SELECT poles.type from poles where poles.id = object.id)
                                        WHEN 'Concreto' THEN
                                            'images/maps_icons/electric-pole-icon.svg'
                                        WHEN 'Madera' THEN
                                            'images/maps_icons/wood-icon.svg'
                                        WHEN 'Metalicos' THEN
                                            'images/maps_icons/steel-icon.svg'
                                        WHEN 'luminarias' THEN
                                            'images/maps_icons/streetlight-svgrepo-com.svg'
                                        ELSE
                                            'images/maps_icons/electric-pole-icon.svg'
                                    END)
                                ELSE
                                    'images/maps_icons/circle-fill.svg'
                            END AS icon";

        if ($option === 'boxIcon')
            return "CASE (SELECT box_types.type from box_types where box_types.id = object.box_type_id)
                            WHEN 'Empalme' THEN
                                'images/maps_icons/box-seam.svg'
                            WHEN 'Primer nivel' THEN
                                'images/maps_icons/inbox-fill.svg'
                            WHEN 'Segundo nivel' THEN
                                'images/maps_icons/inboxes-fill.svg'
                            ELSE
                                'images/maps_icons/box-seam.svg'
                        END AS icon";

        return "CASE
                        WHEN (map_routes.name is not null) THEN
                            CONCAT(map_routes.id, '-', 'route')
                        ELSE
                            '#'
                    END AS parent";
    }
}
