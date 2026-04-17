<?php

namespace App\Repositories;

use App\Models\ActiveEquipment;
use App\Models\Box;
use App\Models\BoxInput;
use App\Models\Card;
use App\Models\PassiveEquipment;
use App\Models\Port;
use App\Models\Site;
use App\Models\Transceiver;
use App\Models\Tray;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Francisco López Zetina
 */
	class PortRepository extends BaseRepository
	{
		public function getModel(): Port
		{
			return new Port();
		}

        public function createByBox(Box $box)
        {
            for ($i = 1 ; $i <= $box->type->ports ; $i++) {
                $this->create([
                    'number'=>$i,
                    'type'=>Port::$box,
                    'portable_id'=>$box->id,
                    'portable_type'=>Box::class,
                ]);
            }
        }

        public function getByFiberForDataTable(int $id, string $type)
        {
            $query = $this->portLinksByObjectDataQuery();

            $query->where('ports.portable_id', $id)
            ->where('ports.portable_type', $type);

            return datatables()->query($query)->toJson();
        }

        public function portLinksByObjectDataQuery()
        {
            return DB::table("ports")
                    ->select(
                        "ports.id",
                        "ports.number",
                        DB::raw("CONCAT(input_box_inputs.number, ' (', input_map_routes.name, ')') as first_box_input_name"),
                        "input_box_inputs.id AS first_box_input_id",
                        "input_fibers.id as first_fiber_id",
                        "input_fibers.number as first_fiber_number",
                        "first_fiber_color.code AS first_fiber_color",
                        "first_fiber_color.name AS first_fiber_color_name",
                        "input_buffers.id as first_buffer_id",
                        "first_buffer_color.code AS first_buffer_color",
                        "first_buffer_color.name AS first_buffer_color_name",
                        "output_box_inputs.id as test_id",
                        DB::raw("CONCAT(output_box_inputs.number,' (', output_map_routes.name, ')') as second_box_input_name"),
                        "output_box_inputs.id AS second_box_input_id",
                        "output_fibers.id as second_fiber_id",
                        "output_fibers.number as second_fiber_number",
                        "second_fiber_color.code AS  second_fiber_color",
                        "second_fiber_color.name AS  second_fiber_color_name",
                        "output_buffers.id as second_buffer_id",
                        "second_buffer_color.code AS second_buffer_color",
                        "second_buffer_color.name AS second_buffer_color_name"
                    )
                    ->leftJoin("equipment_links AS input_equipment_links","input_equipment_links.input_id", "ports.id")
                    ->leftJoin("equipment_links AS output_equipment_links","output_equipment_links.output_id", "ports.id")
                    ->leftJoin("map_links AS input_map_links", "input_map_links.id", "input_equipment_links.map_link_id")
                    ->leftJoin("map_links AS output_map_links", "output_map_links.id", "output_equipment_links.map_link_id")
                    ->leftJoin("box_inputs AS input_box_inputs", function($join){
                        $join->where(function($where){
                            $where->whereColumn("input_box_inputs.id", "input_map_links.input_id")
                                ->where("input_map_links.input_type", BoxInput::class);
                        })
                        ->orWhere(function($where){
                            $where->whereColumn("input_box_inputs.id", "input_map_links.output_id")
                                ->where("input_map_links.output_type", BoxInput::class);
                        });
                    })
                    ->leftJoin("box_inputs AS output_box_inputs", function($join){
                        $join->where(function($where){
                            $where->whereColumn("output_box_inputs.id", "output_map_links.input_id")
                                ->where("output_map_links.input_type", BoxInput::class);
                        })
                        ->orWhere(function($where){
                            $where->whereColumn("output_box_inputs.id", "output_map_links.output_id")
                                ->where("output_map_links.output_type", BoxInput::class);
                        });
                    })
                    ->leftJoin("map_routes AS input_map_routes", "input_map_routes.id", "input_map_links.map_route_id")
                    ->leftJoin("map_routes AS output_map_routes", "output_map_routes.id", "output_map_links.map_route_id")
                    ->leftJoin("fibers AS input_fibers", "input_fibers.id", "input_equipment_links.fiber_id")
                    ->leftJoin("fibers AS output_fibers", "output_fibers.id", "output_equipment_links.fiber_id")
                    ->leftJoin("buffers AS input_buffers", "input_buffers.id", "input_fibers.buffer_id")
                    ->leftJoin("buffers AS output_buffers", "output_buffers.id", "output_fibers.buffer_id")
                    ->leftJoin("colors as first_fiber_color", "first_fiber_color.id", "input_fibers.color_id")
                    ->leftJoin("colors as first_buffer_color", "first_buffer_color.id", "input_buffers.color_id")
                    ->leftJoin("colors as second_fiber_color", "second_fiber_color.id", "output_fibers.color_id")
                    ->leftJoin("colors as second_buffer_color", "second_buffer_color.id", "output_buffers.color_id");
        }

        public function getByDataForDataTable(int $id, string $type, ?string $portType = null)
        {
            $query = DB::table('ports')
                        ->select(
                            'ports.id',
                            'ports.number',
                            'ports.type',
                            'map_routes.name as map_route',
                            'fibers.number as fiber_number',
                            'fiber_colors.code AS fiber_color',
                            'buffers.id as buffer_number',
                            'buffer_colors.code AS buffer_color',
                            DB::raw("
                                CASE
                                    WHEN (ports_linked.portable_type = 'App\\\Models\\\Card') THEN
                                        CONCAT(cards.name, ' (', active_equipments.name ,')')
                                    WHEN (ports_linked.portable_type = 'App\\\Models\\\ActiveEquipment') THEN
                                        active_equipments.name
                                    WHEN (ports_linked.portable_type = 'App\\\Models\\\PassiveEquipment') THEN
                                        passive_equipments.name
                                    WHEN (ports_linked.portable_type = 'App\\\Models\\\Transceiver') THEN
                                        transceivers.description
                                    WHEN (ports_linked.portable_type = 'App\\\Models\\\\tray') THEN
                                        CONCAT( 'Charola ' ,trays.number)
                                    ELSE
                                        boxes.nomenclature
                                END as equipment_name"
                            ),
                            "ports_linked.number AS second_name"
                        )
                        ->leftJoin('equipment_links', function($join){
                            $join
                            ->where(function($query){
                                $query->whereColumn('equipment_links.input_id', 'ports.id');
                            })
                            ->orWhere(function($query){
                                $query->whereColumn('equipment_links.output_id', 'ports.id');
                            });
                        })
                        ->leftJoin("map_links", "map_links.id", "equipment_links.map_link_id")
                        ->leftJoin('map_routes', 'map_routes.id', 'map_links.map_route_id')
                        ->leftJoin('fibers', 'fibers.id', 'equipment_links.fiber_id')
                        ->leftJoin('buffers', 'buffers.id', 'fibers.buffer_id')
                        ->leftJoin('colors AS fiber_colors', 'fiber_colors.id', 'fibers.color_id')
                        ->leftJoin('colors AS buffer_colors', 'buffer_colors.id', 'buffers.color_id')
                        ->leftJoin(DB::raw('ports AS ports_linked'), function ($join){
                            $join
                            ->where(function($query){
                                $query->whereColumn('equipment_links.input_id', 'ports_linked.id')
                                    ->whereColumn('equipment_links.input_id', '<>','ports.id');
                            })
                            ->orWhere(function($query){
                                $query->whereColumn('equipment_links.output_id', 'ports_linked.id')
                                    ->whereColumn('equipment_links.output_id', '<>','ports.id');
                            });
                        })
                        ->leftJoin('cards', function ($query){
                            $query->whereColumn('cards.id', 'ports_linked.portable_id')
                                ->where('ports_linked.portable_type', Card::class);
                        })
                        ->leftJoin('boxes', function ($join){
                            $join->whereColumn('boxes.id', 'ports_linked.portable_id')
                                ->where('ports_linked.portable_type', Box::class);
                        })
                        ->leftJoin('active_equipments', function ($join){
                            $join->whereColumn('active_equipments.id', 'cards.active_equipment_id')
                                ->orWhere(function($join){
                                    $join->whereColumn('active_equipments.id', 'ports_linked.portable_id')
                                        ->where('ports_linked.portable_type', ActiveEquipment::class);
                                });
                        })
                        ->leftJoin('passive_equipments', function($join){
                            $join->whereColumn('passive_equipments.id', 'ports_linked.portable_id')
                                ->where('ports_linked.portable_type', PassiveEquipment::class);
                        })
                        ->leftJoin('transceivers', function($join){
                            $join->whereColumn('transceivers.id', 'ports_linked.portable_id')
                                ->where('ports_linked.portable_type', Transceiver::class);
                        })
                        ->leftJoin('trays', function($join){
                            $join->whereColumn('trays.id', 'ports_linked.portable_id')
                                ->where('ports_linked.portable_type', Tray::class);
                        })
                        ->where('ports.portable_id', $id)
                        ->where('ports.portable_type', $type);

            if(!empty($portType))
                $query->where('ports.type', $portType);

            return datatables()->query($query)->toJson();
        }

        public function getForDataTableSpliter(int $id, string $type, ?string $portType = null)
        {
            $query = DB::table('ports')
                        ->select(
                            'ports.id',
                            'ports.number',
                            'ports.type',
                            DB::raw("
                                CASE
                                    WHEN (box_inputs.number is not null) THEN
                                        CONCAT( box_inputs.number, ' (',  map_routes.name, ')')
                                    ELSE
                                        CONCAT(splitter_in_box_inputs.number, ' (',  map_routes.name, ')')
                                END as map_route
                            "),
                            'fibers.number as fiber_number',
                            'fiber_colors.code AS fiber_color',
                            'buffers.id as buffer_number',
                            'buffer_colors.code AS buffer_color',
                            DB::raw("
                                CASE
                                    WHEN (ports_linked.portable_type = 'App\\\Models\\\\tray') THEN
                                        CONCAT( 'Charola ' ,trays.number)
                                    WHEN (ports_linked.portable_type = 'App\\\Models\\\Box') THEN
                                        CONCAT( 'Caja (', boxes.nomenclature, ')')
                                END as equipment_name"
                            ),
                            "ports_linked.number AS second_name"
                        )
                        ->leftJoin('equipment_links', function($join){
                            $join->whereColumn('equipment_links.input_id', 'ports.id')
                            ->orWhereColumn('equipment_links.output_id', 'ports.id');
                        })
                        ->leftJoin(DB::raw('ports AS ports_linked'), function ($join){
                            $join
                            ->where(function($query){
                                $query->whereColumn('equipment_links.input_id', 'ports_linked.id')
                                    ->whereColumn('equipment_links.input_id', '<>','ports.id');
                            })
                            ->orWhere(function($query){
                                $query->whereColumn('equipment_links.output_id', 'ports_linked.id')
                                    ->whereColumn('equipment_links.output_id', '<>','ports.id');
                            });
                        })
                        ->leftJoin("equipment_links as fusion_equipment_links", function($join){
                            $join
                            ->where(function($query){
                                $query->whereColumn('fusion_equipment_links.input_id', 'ports_linked.id')
                                    ->whereColumn('fusion_equipment_links.output_id', '!=', 'ports.id');
                            })
                            ->orWhere(function($query){
                                $query->whereColumn('fusion_equipment_links.output_id', 'ports_linked.id')
                                        ->whereColumn('fusion_equipment_links.input_id', '!=', 'ports.id');
                            });
                        })
                        ->leftJoin(DB::raw('ports AS ports_fision'), function ($join){
                            $join
                            ->where(function($query){
                                $query->whereColumn('fusion_equipment_links.input_id', 'ports_fision.id')
                                    ->whereColumn('fusion_equipment_links.input_id', '!=','ports_linked.id');
                            })
                            ->orWhere(function($query){
                                $query->whereColumn('fusion_equipment_links.output_id', 'ports_fision.id')
                                    ->whereColumn('fusion_equipment_links.output_id', '!=','ports_linked.id');
                            });
                        })
                        ->leftJoin('trays', function($join){
                            $join->whereColumn('trays.id', 'ports_linked.portable_id')
                                ->where('ports_linked.portable_type', Tray::class);
                        })
                        ->leftJoin('boxes', function($join){
                            $join->whereColumn('boxes.id', 'ports_linked.portable_id')
                                ->where('ports_linked.portable_type', Box::class);
                        })
                        ->leftJoin("box_inputs", function($join){
                            $join->whereColumn('box_inputs.id', 'ports_fision.portable_id')
                                ->where('ports_fision.portable_type', BoxInput::class);
                        })
                        ->leftJoin('box_inputs as splitter_in_box_inputs', function($join){
                            $join->whereColumn('splitter_in_box_inputs.id', 'ports_linked.portable_id')
                                ->where('ports_linked.portable_type', BoxInput::class);
                        })
                        ->leftJoin("map_links", "map_links.id", "fusion_equipment_links.map_link_id")
                        ->leftJoin('map_routes', 'map_routes.id', 'map_links.map_route_id')
                        ->where('ports.portable_id', $id)
                        ->where('ports.portable_type', $type);

            if($portType === "splitter_out"){
                $query->leftJoin('fibers', 'fibers.id', 'fusion_equipment_links.fiber_id');
            }else{
                $query->leftJoin('fibers', 'fibers.id', 'equipment_links.fiber_id');
            }

            $query->leftJoin('buffers', 'buffers.id', 'fibers.buffer_id')
            ->leftJoin('colors AS fiber_colors', 'fiber_colors.id', 'fibers.color_id')
            ->leftJoin('colors AS buffer_colors', 'buffer_colors.id', 'buffers.color_id');

            if(!empty($portType))
                $query->where('ports.type', $portType);

            return datatables()->query($query)->toJson();
        }

        public function getForSpecialTable(int $id)
        {
            DB::enableQueryLog();
            $collection = collect(DB::select("
                SELECT
                    y as fila,
                    max(CASE WHEN (number - ((CEILING(number/12) - 1)*12)) = 1 THEN number ELSE null END) AS '1',
                    max(CASE WHEN (number - ((CEILING(number/12) - 1)*12)) = 2 THEN number ELSE null END) AS '2',
                    max(CASE WHEN (number - ((CEILING(number/12) - 1)*12)) = 3 THEN number ELSE null END) AS '3',
                    max(CASE WHEN (number - ((CEILING(number/12) - 1)*12)) = 4 THEN number ELSE null END) AS '4',
                    max(CASE WHEN (number - ((CEILING(number/12) - 1)*12)) = 5 THEN number ELSE null END) AS '5',
                    max(CASE WHEN (number - ((CEILING(number/12) - 1)*12)) = 6 THEN number ELSE null END) AS '6',
                    max(CASE WHEN (number - ((CEILING(number/12) - 1)*12)) = 7 THEN number ELSE null END) AS '7',
                    max(CASE WHEN (number - ((CEILING(number/12) - 1)*12)) = 8 THEN number ELSE null END) AS '8',
                    max(CASE WHEN (number - ((CEILING(number/12) - 1)*12)) = 9 THEN number ELSE null END) AS '9',
                    max(CASE WHEN (number - ((CEILING(number/12) - 1)*12)) = 10 THEN number ELSE null END) AS '10',
                    max(CASE WHEN (number - ((CEILING(number/12) - 1)*12)) = 11 THEN number ELSE null END) AS '11',
                    max(CASE WHEN (number - ((CEILING(number/12) - 1)*12)) = 12 THEN number ELSE null END) AS '12'
                FROM
                    (
                        SELECT
                            *,
                            (number - ((CEILING(number/12) - 1)*12)) AS x,
                            (CEILING(number/12)) AS y
                        FROM
                            ports
                        WHERE
                            portable_id = $id
                            AND portable_type = 'App\\\Models\\\PassiveEquipment'
                    ) as ports
                GROUP BY
                    y
            "));

            return datatables()->collection($collection)->toJson();
        }

        public function destroyByObject($object): bool
        {
            if($object::class === Box::class)
                return false;

            $ports = $object->ports;

            if(empty($ports))
                return false;

            $equipmentLinkRespository = new EquipmentLinkRepository();

            $ports->map(function($port) use($equipmentLinkRespository){
                $equipmentLinkRespository->disconnectPort($port->id);
                $this->delete($port);
            });

            return true;
        }

        public function createByData($object,int $numberStart, array $data): Collection
        {
            $contador = $numberStart;
            foreach($data as $type => $number){
                for ($i= 0 ; $i < $number ; $i++) {
                    $this->create([
                        'number' => $contador,
                        'type' => $type,
                        'portable_id'=>$object->id,
                        'portable_type'=>$object::class,
                    ]);
                    $contador++;
                }
            }

            return $object->ports;
        }

        public function findByObjectAndName(int $objectId, string $objectType, int $number): Port
        {
            return $this->getModel()
                        ->where([
                            ["portable_id", $objectId],
                            ["portable_type", $objectType],
                            ["number", $number]
                        ])
                        ->first();
        }

        public function findOtherPort(?int $objectId, ?string $objectType, ?int $number, int $portId): ?Port
        {
            return $this->getModel()
                    ->where([
                        ["portable_id", $objectId],
                        ["portable_type", $objectType],
                        ["number", $number],
                        ["id", "<>", $portId]
                    ])
                    ->first();
        }

        public function SearchForSelectByObject(?string $text, int $page, int $objectId, string $objectClass, array $withOutIds)
        {
            $querry =  DB::table('ports')
                        ->select(
                            "ports.id",
                            "ports.number AS text"
                        )
                        ->where('ports.portable_id', $objectId)
                        ->where('ports.portable_type', $objectClass)
                        ->orderBy("ports.number");

            if(!empty($text)){
                $querry = $querry->where('ports.number', 'LIKE', "%$text%");
            }

            if(!empty($withOutIds)){
                $querry = $querry->whereNotIn('ports.id', $withOutIds);
            }

            return $querry->paginate(7, $page);
        }

        public function getByObject(int $objectId, string $objectModelClass): Collection
        {
            return $this->getModel()
                        ->where([
                            ["portable_id", $objectId],
                            ["portable_type", $objectModelClass],
                        ])
                        ->get();
        }

        public function getByData(int $boxInputId, int $fiberId)
        {
            $inputQuery = $this->getModel()
                        ->select(
                            "ports.*"
                        )
                        ->join("equipment_links","equipment_links.input_id", "ports.id")
                        ->join("map_links", "map_links.id", "equipment_links.map_link_id")
                        ->where(function($where) use($boxInputId){
                            $where
                            ->where(function($where) use($boxInputId){
                                $where->where("map_links.input_id", $boxInputId)
                                    ->where("map_links.input_type", BoxInput::class);
                            })
                            ->orWhere(function($where) use($boxInputId){
                                $where->where("map_links.output_id", $boxInputId)
                                    ->where("map_links.output_type", BoxInput::class);
                            });
                        })
                        ->where("ports.portable_id", $boxInputId)
                        ->where("ports.portable_type", BoxInput::class)
                        ->where("equipment_links.fiber_id", $fiberId);

            return $this->getModel()
                        ->select(
                            "ports.*"
                        )
                        ->join("equipment_links","equipment_links.output_id", "ports.id")
                        ->join("map_links", "map_links.id", "equipment_links.map_link_id")
                        ->where(function($where) use($boxInputId){
                            $where
                            ->where(function($where) use($boxInputId){
                                $where->where("map_links.input_id", $boxInputId)
                                    ->where("map_links.input_type", BoxInput::class);
                            })
                            ->orWhere(function($where) use($boxInputId){
                                $where->where("map_links.output_id", $boxInputId)
                                    ->where("map_links.output_type", BoxInput::class);
                            });
                        })
                        ->where("ports.portable_id", $boxInputId)
                        ->where("ports.portable_type", BoxInput::class)
                        ->where("equipment_links.fiber_id", $fiberId)
                        ->union($inputQuery)
                        ->first();
        }

        public function getByMapRouteAndFiber(int $mapRouteId, int $fiberId) : Collection
        {
            return $this->getModel()
                        ->select("ports.*")
                        ->leftJoin("equipment_links", function($join){
                            $join->on("equipment_links.input_id", "ports.id")
                            ->orWhereColumn("equipment_links.output_id", "ports.id");
                        })
                        ->leftJoin("map_links", "map_links.id", "equipment_links.map_link_id")
                        ->where('equipment_links.fiber_id', $fiberId)
                        ->where('map_links.map_route_id', $mapRouteId)
                        ->groupBy(
                            "ports.id"
                        )
                        ->get();
        }

        public function findSitePortUnconnected(int $siteId){
            return $this->getModel()
                        ->select("ports.*")
                        ->leftJoin("equipment_links", function($join){
                            $join->on("equipment_links.input_id", "ports.id")
                            ->orWhereColumn("equipment_links.output_id", "ports.id");
                        })
                        ->where("ports.portable_id", $siteId)
                        ->where("ports.portable_type", Site::class)
                        ->whereNull("equipment_links.id")
                        ->first();
        }

        public function createContinuousPort(int $portableId, string $portableType): Port
        {
            $amountPorts = DB::table("ports")->where([
                ["portable_type", $portableType],
                ["portable_id", $portableId],
                ["type", Port::$continuo]
            ])->count();

            return $this->create([
                'number' => $amountPorts + 1,
                'type' => Port::$continuo,
                'portable_id' => $portableId,
                'portable_type'=> $portableType,
            ]);
        }

        public function findOrCreateAvailablePort(int $objectId, string $objectType, int $number, string $type, int $mapRouteId) : Port
        {
            $object = $this->findAvailablePortByAllData($objectId, $objectType, $number, $type, $mapRouteId);

            if(!empty($object))
                return $object;

            return $this->create([
                'number' => $number,
                'type' => $type,
                'portable_id' => $objectId,
                'portable_type'=> $objectType,
            ]);
        }

        public function findAvailablePortByAllData(int $objectId, string $objectType, int $number, string $type, int $mapRouteId) : ?Port
        {
            return Port::select([
                    "ports.*",
                    "amount_links"=>function($query){
                        $query->from("equipment_links")
                        ->select(DB::raw("COUNT(*)"))
                        ->whereColumn("equipment_links.input_id", "ports.id")
                        ->orWhereColumn("equipment_links.output_id", "ports.id");
                    }
                ])
                ->where([
                    ["portable_id", $objectId],
                    ["portable_type", $objectType],
                    ["number", $number],
                    ["type", $type]
                ])
                ->leftJoin("equipment_links", function($join){
                    $join->on("equipment_links.input_id", "ports.id")
                        ->orWhereColumn("equipment_links.output_id", "ports.id");
                })
                ->leftJoin("map_links", "map_links.id", "equipment_links.map_link_id")
                ->where("map_links.map_route_id", $mapRouteId)
                ->having("amount_links", "<", 2)
                ->groupBy(
                    "ports.id",
                    "ports.number",
                    "ports.type",
                    "ports.portable_id",
                    "ports.portable_type",
                    "ports.created_by",
                    "ports.updated_by",
                    "ports.created_at",
                    "ports.updated_at"
                )
                ->first();
        }
	}
?>
