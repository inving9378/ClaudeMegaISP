<?php

namespace App\Repositories;

use App\Models\Box;
use App\Models\BoxInput;
use App\Models\CutFiber;
use App\Models\Point;
use App\Models\Pole;
use App\Models\Site;
use App\Models\Splitter;
use App\Models\Tray;
use App\Models\Trench;
use App\Repositories\BaseRepository;
use Illuminate\Support\Facades\DB;

/**
 * Francisco López Zetina
 */
	class BoxInputRepository extends BaseRepository
	{
		public function getModel(): BoxInput
		{
			return new BoxInput();
		}

        public function createByBox(Box $box)
        {
            for ($i = 1 ; $i < ($box->type->inputs + 1); $i++) {
                $this->create([
                    "number"=>$i,
                    "box_id"=>$box->id
                ]);
            }
        }

        public function list(?string $text, int $boxId, int $page)
        {
            $querry = DB::table("box_inputs")
                        ->select(
                            "box_inputs.id",
                            "box_inputs.number AS text"
                        )
                        ->leftJoin("map_links", function($join){
                            $join
                            ->where(function($query){
                                $query->whereColumn('map_links.input_id', 'box_inputs.id')
                                ->where("map_links.input_type", BoxInput::class);
                            })
                            ->orWhere(function($query){
                                $query->whereColumn('map_links.output_id', 'box_inputs.id')
                                ->where("map_links.output_type", BoxInput::class);
                            });
                        })
                        ->where("box_id", $boxId)
                        ->whereNull("map_links.id");

            if(!empty($text)){
                $querry = $querry->where("number", 'LIKE', "%$text%");
            }

            return $querry->paginate(7, $page);
        }

        public function getByBoxForDatatable(int $boxId)
        {
            $boxSubQuery = DB::table("boxes")
                        ->select(
                            "boxes.*",
                            "bi.id AS box_input_id"
                        )
                        ->join("box_inputs as bi", "bi.box_id", "boxes.id");


            $tableRepository = new TableRepository();

            $tables = $tableRepository->getHasPosition(true);

            $query = DB::table("box_inputs")
                        ->select(
                            "box_inputs.id",
                            "box_inputs.number",
                            "map_routes.name AS route",
                            DB::raw($this->getCaseForColumnSearchMapLink($tables)),
                        )
                        ->leftJoin("map_links", function ($join) {
                            $join->where(function($where){
                                $where->whereColumn("map_links.output_id", "=", "box_inputs.id")
                                    ->where("map_links.output_type", BoxInput::class);
                            })
                            ->orWhere(function($where){
                                $where->whereColumn("map_links.input_id", "=", "box_inputs.id")
                                    ->where("map_links.input_type", BoxInput::class);
                            });
                        })
                        ->leftJoin("map_routes", function($join){
                            $join->on("map_routes.id", "map_links.map_route_id");
                        })
                        ->leftJoin("sites", function ($join) {
                            $join->where(function($where){
                                $where->whereColumn('sites.id', '=', 'map_links.input_id')
                                    ->where('map_links.input_type', Site::class)
                                    ->whereColumn("map_links.output_id", "box_inputs.id")
                                    ->where("map_links.output_type", BoxInput::class);
                            })
                            ->orWhere(function($where){
                                $where->whereColumn('sites.id', '=', 'map_links.output_id')
                                    ->where('map_links.output_type', Site::class)
                                    ->whereColumn("map_links.input_id", "box_inputs.id")
                                    ->where("map_links.input_type", BoxInput::class);
                            });
                        })
                        ->leftJoinSub($boxSubQuery, "boxes", function ($join) {
                            $join->where(function($where){
                                $where->whereColumn('sites.id', '=', 'map_links.input_id')
                                    ->where('map_links.input_type', Site::class)
                                    ->whereColumn("map_links.output_id", "box_inputs.id")
                                    ->where("map_links.output_type", BoxInput::class);
                            })
                            ->orWhere(function($where){
                                $where->whereColumn('sites.id', '=', 'map_links.output_id')
                                    ->where('map_links.output_type', Site::class)
                                    ->whereColumn("map_links.input_id", "box_inputs.id")
                                    ->where("map_links.input_type", BoxInput::class);
                            });
                        })
                        ->leftJoin("poles", function ($join) {
                            $join->where(function($where){
                                $where->whereColumn('poles.id', '=', 'map_links.input_id')
                                    ->where('map_links.input_type', Pole::class)
                                    ->whereColumn("map_links.output_id", "box_inputs.id")
                                    ->where("map_links.output_type", BoxInput::class);
                            })
                            ->orWhere(function($where){
                                $where->whereColumn('poles.id', '=', 'map_links.output_id')
                                    ->where('map_links.output_type', Pole::class)
                                    ->whereColumn("map_links.input_id", "box_inputs.id")
                                    ->where("map_links.input_type", BoxInput::class);
                            });
                        })
                        ->leftJoin("cut_fibers", function ($join) {
                            $join->where(function($where){
                                $where->whereColumn('cut_fibers.id', '=', 'map_links.input_id')
                                    ->where('map_links.input_type', CutFiber::class)
                                    ->whereColumn("map_links.output_id", "box_inputs.id")
                                    ->where("map_links.output_type", BoxInput::class);
                            })
                            ->orWhere(function($where){
                                $where->whereColumn('cut_fibers.id', '=', 'map_links.output_id')
                                    ->where('map_links.output_type', CutFiber::class)
                                    ->whereColumn("map_links.input_id", "box_inputs.id")
                                    ->where("map_links.input_type", BoxInput::class);
                            });
                        })
                        ->leftJoin("points", function ($join) {
                            $join->where(function($where){
                                $where->whereColumn('points.id', 'map_links.input_id')
                                    ->where('map_links.input_type', Point::class)
                                    ->whereColumn("map_links.output_id", "box_inputs.id")
                                    ->where("map_links.output_type", BoxInput::class);
                            })
                            ->orWhere(function($where){
                                $where->whereColumn('points.id', 'map_links.output_id')
                                    ->where('map_links.output_type', Point::class)
                                    ->whereColumn("map_links.input_id", "box_inputs.id")
                                    ->where("map_links.input_type", BoxInput::class);
                            });
                        })
                        ->leftJoin("trenches", function ($join) {
                            $join->where(function($where){
                                $where->whereColumn('trenches.id', '=', 'map_links.input_id')
                                    ->where('map_links.input_type', Trench::class)
                                    ->whereColumn("map_links.output_id", "box_inputs.id")
                                    ->where("map_links.output_type", BoxInput::class);
                            })
                            ->orWhere(function($where){
                                $where->whereColumn('trenches.id', '=', 'map_links.output_id')
                                    ->where('map_links.output_type', Trench::class)
                                    ->whereColumn("map_links.input_id", "box_inputs.id")
                                    ->where("map_links.input_type", BoxInput::class);
                            });
                        })
                        ->where("box_inputs.box_id", $boxId);

            return datatables()->query($query)->toJson();
        }

        public function destroyByObject($object)
        {
            if($object::class !== Box::class)
                return false;

            $inputs = $object->inputs;

            $equipmentLinkRespository = new EquipmentLinkRepository();

            $inputs->map(function($input) use($equipmentLinkRespository){
                $equipmentLinkRespository->disconnectPort($input->id);
                $this->delete($input);
            });

            return true;
        }

        public function listForFusion(?string $text, int $page, int $portId)
        {
            $querry = DB::table("box_inputs")
                        ->select(
                            "box_inputs.id",
                            DB::raw("CONCAT( box_inputs.number, ' (', map_routes.name, ')') AS text")
                        )
                        ->join("map_links", function($join){
                            $join
                            ->where(function($query){
                                $query->whereColumn('map_links.input_id', 'box_inputs.id')
                                ->where("map_links.input_type", BoxInput::class);
                            })
                            ->orWhere(function($query){
                                $query->whereColumn('map_links.output_id', 'box_inputs.id')
                                ->where("map_links.output_type", BoxInput::class);
                            });
                        })
                        ->join("map_routes", "map_routes.id", "map_links.map_route_id")
                        ->join("trays", "trays.box_id", "box_inputs.box_id")
                        ->join("ports", function($join){
                            $join->on("ports.portable_id", "trays.id")
                                ->where("ports.portable_type", Tray::class);
                        })
                        ->where("ports.id", $portId);

            if(!empty($text))
                $querry = $querry->where("name", 'LIKE', "%$text%");

            return $querry->paginate(7, $page);
        }
        public function listForSplitterIn(?string $text, int $page, int $portId)
        {
            $querry = DB::table("box_inputs")
                        ->select(
                            "box_inputs.id",
                            DB::raw("CONCAT( box_inputs.number, ' (', map_routes.name, ')') AS text")
                        )
                        ->join("map_links", function($join){
                            $join
                            ->where(function($query){
                                $query->whereColumn('map_links.input_id', 'box_inputs.id')
                                ->where("map_links.input_type", BoxInput::class);
                            })
                            ->orWhere(function($query){
                                $query->whereColumn('map_links.output_id', 'box_inputs.id')
                                ->where("map_links.output_type", BoxInput::class);
                            });
                        })
                        ->join("map_routes", "map_routes.id", "map_links.map_route_id")
                        ->join("splitters", "splitters.box_id", "box_inputs.box_id")
                        ->join("ports", function($join){
                            $join->on("ports.portable_id", "splitters.id")
                                ->where("ports.portable_type", Splitter::class);
                        })
                        ->where("ports.id", $portId);

            if(!empty($text))
                $querry = $querry->where("name", 'LIKE', "%$text%");

            return $querry->paginate(7, $page);
        }
	}
?>
