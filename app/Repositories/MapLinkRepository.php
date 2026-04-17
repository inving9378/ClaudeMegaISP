<?php

namespace App\Repositories;

use App\Models\Box;
use App\Models\BoxInput;
use App\Models\CutFiber;
use App\Models\MapLink;
use App\Models\Point;
use App\Models\Pole;
use App\Models\Site;
use App\Models\Splitter;
use App\Models\Trench;
use App\Repositories\BaseRepository;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Francisco López Zetina
 */
class MapLinkRepository extends BaseRepository
{
    public function getModel(): MapLink
    {
        return new MapLink();
    }

    public function getMapLinksFiltered(int $mapProyectId, ?array $visibleRoutes)
    {
        return DB::table($this->getModel()->getTable())
                ->select(
                    'map_links.id',
                    'map_links.map_route_id',
                    'map_links.tube_id',
                    "colors.code AS color",
                    DB::raw("ST_X(
                            CASE
                                WHEN (input_sites.id is not null) THEN
                                    input_sites.point
                                WHEN (input_boxes.id is not null) THEN
                                    input_boxes.point
                                WHEN (input_poles.id is not null) THEN
                                    input_poles.point
                                WHEN (input_cut_fibers.id is not null) THEN
                                    input_cut_fibers.point
                                WHEN (input_points.id is not null) THEN
                                    input_points.point
                                WHEN (input_trenches.id is not null) THEN
                                    input_trenches.point
                                ELSE
                                    null
                            END
                        ) AS input_longitude"),
                    DB::raw("ST_Y(
                            CASE
                                WHEN (input_sites.id is not null) THEN
                                    input_sites.point
                                WHEN (input_boxes.id is not null) THEN
                                    input_boxes.point
                                WHEN (input_poles.id is not null) THEN
                                    input_poles.point
                                WHEN (input_cut_fibers.id is not null) THEN
                                    input_cut_fibers.point
                                WHEN (input_points.id is not null) THEN
                                    input_points.point
                                WHEN (input_trenches.id is not null) THEN
                                    input_trenches.point
                                ELSE
                                    null
                            END
                        ) AS input_latitude"
                    ),
                    DB::raw("ST_X(
                            CASE
                                WHEN (output_sites.id is not null) THEN
                                    output_sites.point
                                WHEN (output_boxes.id is not null) THEN
                                    output_boxes.point
                                WHEN (output_poles.id is not null) THEN
                                    output_poles.point
                                WHEN (output_cut_fibers.id is not null) THEN
                                    output_cut_fibers.point
                                WHEN (output_points.id is not null) THEN
                                    output_points.point
                                WHEN (output_trenches.id is not null) THEN
                                    output_trenches.point
                                ELSE
                                    null
                            END
                        ) AS output_longitude"
                    ),
                    DB::raw("ST_Y(
                            CASE
                                WHEN (output_sites.id is not null) THEN
                                    output_sites.point
                                WHEN (output_boxes.id is not null) THEN
                                    output_boxes.point
                                WHEN (output_poles.id is not null) THEN
                                    output_poles.point
                                WHEN (output_cut_fibers.id is not null) THEN
                                    output_cut_fibers.point
                                WHEN (output_points.id is not null) THEN
                                    output_points.point
                                WHEN (output_trenches.id is not null) THEN
                                    output_trenches.point
                                ELSE
                                    null
                            END
                        ) AS output_latitude"
                    ),
                )
                ->leftJoin("map_routes", "map_routes.id", "map_links.map_route_id")
                ->leftJoin("colors", "colors.id", "map_routes.color_id")
                ->leftJoin(
                    DB::raw("(SELECT sites.*, positions.point FROM sites JOIN positions ON positions.positionable_id = sites.id AND positions.positionable_type = 'App\\\Models\\\Site' WHERE sites.map_proyect_id = $mapProyectId ) AS input_sites"),
                    function ($join) {
                        $join->on('input_sites.id', '=', 'map_links.input_id')
                            ->where('map_links.input_type', Site::class);
                    }
                )
                ->leftJoin(
                    DB::raw("(SELECT boxes.*, positions.point, box_inputs.id AS box_input_id FROM boxes JOIN box_inputs ON box_inputs.box_id = boxes.id JOIN positions ON positions.positionable_id = boxes.id AND positionable_type = 'App\\\Models\\\Box' WHERE boxes.map_proyect_id = $mapProyectId) AS input_boxes"),
                    function ($join) {
                        $join->on('input_boxes.box_input_id', '=', 'map_links.input_id')
                            ->where('map_links.input_type', BoxInput::class);
                    }
                )
                ->leftJoin(
                    DB::raw("(SELECT poles.*, positions.point FROM poles JOIN positions ON positions.positionable_id = poles.id AND positionable_type = 'App\\\Models\\\Pole' WHERE poles.map_proyect_id = $mapProyectId ) AS input_poles"),
                    function ($join) {
                        $join->on('input_poles.id', '=', 'map_links.input_id')
                            ->where('map_links.input_type', Pole::class);
                    }
                )
                ->leftJoin(
                    DB::raw("(SELECT cut_fibers.*, positions.point FROM cut_fibers JOIN positions ON positions.positionable_id = cut_fibers.id AND positionable_type = 'App\\\Models\\\CubFiber' WHERE cut_fibers.map_proyect_id = $mapProyectId ) AS input_cut_fibers"),
                    function ($join) {
                        $join->on('input_cut_fibers.id', '=', 'map_links.input_id')
                            ->where('map_links.input_type', CutFiber::class);
                    }
                )
                ->leftJoin(
                    DB::raw("(SELECT points.*, positions.point FROM points JOIN positions ON positions.positionable_id = points.id AND positionable_type = 'App\\\Models\\\Point' WHERE points.map_proyect_id = $mapProyectId ) AS input_points"),
                    function ($join) {
                        $join->on('input_points.id', '=', 'map_links.input_id')
                            ->where('map_links.input_type', Point::class);
                    }
                )
                ->leftJoin(
                    DB::raw("(SELECT trenches.*, positions.point FROM trenches JOIN positions ON positions.positionable_id = trenches.id AND positionable_type = 'App\\\Models\\\Trench' WHERE trenches.map_proyect_id = $mapProyectId ) AS input_trenches"),
                    function ($join) {
                        $join->on('input_trenches.id', '=', 'map_links.input_id')
                            ->where('map_links.input_type', Trench::class);
                    }
                )
                ->leftJoin(
                    DB::raw("(SELECT sites.*, positions.point FROM sites JOIN positions ON positions.positionable_id = sites.id AND positionable_type = 'App\\\Models\\\Site' WHERE sites.map_proyect_id = $mapProyectId ) AS output_sites"),
                    function ($join) {
                        $join->on('output_sites.id', '=', 'map_links.output_id')
                            ->where('map_links.output_type', Site::class);
                    }
                )
                ->leftJoin(
                    DB::raw("(SELECT boxes.*, positions.point, box_inputs.id AS box_input_id FROM boxes JOIN box_inputs ON box_inputs.box_id = boxes.id JOIN positions ON positions.positionable_id = boxes.id AND positionable_type = 'App\\\Models\\\Box' WHERE boxes.map_proyect_id = $mapProyectId ) AS output_boxes"),
                    function ($join) {
                        $join->on('output_boxes.box_input_id', '=', 'map_links.output_id')
                            ->where('map_links.output_type', BoxInput::class);
                    }
                )
                ->leftJoin(
                    DB::raw("(SELECT poles.*, positions.point FROM poles JOIN positions ON positions.positionable_id = poles.id AND positionable_type = 'App\\\Models\\\Pole' WHERE poles.map_proyect_id = $mapProyectId ) AS output_poles"),
                    function ($join) {
                        $join->on('output_poles.id', '=', 'map_links.output_id')
                            ->where('map_links.output_type', Pole::class);
                    }
                )
                ->leftJoin(
                    DB::raw("(SELECT cut_fibers.*, positions.point FROM cut_fibers JOIN positions ON positions.positionable_id = cut_fibers.id AND positionable_type = 'App\\\Models\\\CubFiber' WHERE cut_fibers.map_proyect_id = $mapProyectId ) AS output_cut_fibers"),
                    function ($join) {
                        $join->on('output_cut_fibers.id', '=', 'map_links.output_id')
                            ->where('map_links.output_type', CutFiber::class);
                    }
                )
                ->leftJoin(
                    DB::raw("(SELECT points.*, positions.point FROM points JOIN positions ON positions.positionable_id = points.id AND positionable_type = 'App\\\Models\\\Point' WHERE points.map_proyect_id = $mapProyectId ) AS output_points"),
                    function ($join) {
                        $join->on('output_points.id', '=', 'map_links.output_id')
                            ->where('map_links.output_type', Point::class);
                    }
                )
                ->leftJoin(
                    DB::raw("(SELECT trenches.*, positions.point FROM trenches JOIN positions ON positions.positionable_id = trenches.id AND positionable_type = 'App\\\Models\\\Trench' WHERE trenches.map_proyect_id = $mapProyectId ) AS output_trenches"),
                    function ($join) {
                        $join->on('output_trenches.id', '=', 'map_links.output_id')
                            ->where('map_links.output_type', Trench::class);
                    }
                )
                ->whereNotIn('map_links.map_route_id', $visibleRoutes)
                ->where('map_routes.map_proyect_id', $mapProyectId)
                ->get();
    }

    public function getMapLinksByObjectForDataTable(int $objectId, string $objectModelClass)
    {
        $tableRepository = new TableRepository();

        $tables = $tableRepository->getHasPosition(true);

        $route = route('maps.map_link.destroy');

        $boxSubQuery = DB::table("boxes")
                        ->select(
                            "boxes.*",
                            "box_inputs.id AS box_input_id"
                        )
                        ->join("box_inputs", "box_inputs.box_id", "boxes.id");

        $a = DB::table('map_links')
            ->select(
                "map_links.id as map_link_id",
                "map_links.output_id AS id",
                DB::raw($this->getCaseForColumnSearchMapLink($tables)),
                DB::raw($this->whenBuildObjects($tables)),
                "map_proyects.name AS proyect_name",
                DB::raw("'$route' as actions")
            )
            ->leftJoin("sites", function ($join) {
                $join->on('sites.id', '=', 'map_links.output_id')
                    ->where('map_links.output_type', Site::class);
            })
            ->leftJoinSub($boxSubQuery, "boxes", function ($join) {
                $join->on('boxes.box_input_id', '=', 'map_links.output_id')
                    ->where('map_links.output_type', BoxInput::class);
            })
            ->leftJoin("poles", function ($join) {
                $join->on('poles.id', '=', 'map_links.output_id')
                    ->where('map_links.output_type', Pole::class);
            })
            ->leftJoin("cut_fibers", function ($join) {
                $join->on('cut_fibers.id', '=', 'map_links.output_id')
                    ->where('map_links.output_type', CutFiber::class);
            })
            ->leftJoin("points", function ($join) {
                $join->on('points.id', '=', 'map_links.output_id')
                    ->where('map_links.output_type', Point::class);
            })
            ->leftJoin("trenches", function ($join) {
                $join->on('trenches.id', '=', 'map_links.output_id')
                    ->where('map_links.output_type', Trench::class);
            })
            ->leftJoin("map_proyects", function ($join) {
                $join->on("map_proyects.id", "sites.map_proyect_id")
                    ->orWhereColumn("map_proyects.id", "boxes.map_proyect_id")
                    ->orWhereColumn("map_proyects.id", "poles.map_proyect_id")
                    ->orWhereColumn("map_proyects.id", "cut_fibers.map_proyect_id")
                    ->orWhereColumn("map_proyects.id", "points.map_proyect_id");
            })
            ->where('map_links.input_id', $objectId)
            ->where('map_links.input_type', $objectModelClass);

        $b = DB::table('map_links')
            ->select(
                "map_links.id as map_link_id",
                "map_links.input_id AS id",
                DB::raw($this->getCaseForColumnSearchMapLink($tables)),
                DB::raw($this->whenBuildObjects($tables)),
                "map_proyects.name AS proyect_name",
                DB::raw("'$route' as actions")
            )
            ->leftJoin("sites", function ($join) {
                $join->on('sites.id', '=', 'map_links.input_id')
                    ->where('map_links.input_type', Site::class);
            })
            ->leftJoinSub($boxSubQuery, "boxes", function ($join) {
                $join->on('boxes.box_input_id', '=', 'map_links.input_id')
                    ->where('map_links.input_type', BoxInput::class);
            })
            ->leftJoin("poles", function ($join) {
                $join->on('poles.id', '=', 'map_links.input_id')
                    ->where('map_links.input_type', Pole::class);
            })
            ->leftJoin("cut_fibers", function ($join) {
                $join->on('cut_fibers.id', '=', 'map_links.input_id')
                    ->where('map_links.input_type', CutFiber::class);
            })
            ->leftJoin("points", function ($join) {
                $join->on('points.id', '=', 'map_links.input_id')
                    ->where('map_links.input_type', Point::class);
            })
            ->leftJoin("trenches", function ($join) {
                $join->on('trenches.id', '=', 'map_links.input_id')
                    ->where('map_links.input_type', Trench::class);
            })
            ->leftJoin("map_proyects", function ($join) {
                $join->on("map_proyects.id", "sites.map_proyect_id")
                    ->orWhereColumn("map_proyects.id", "boxes.map_proyect_id")
                    ->orWhereColumn("map_proyects.id", "poles.map_proyect_id")
                    ->orWhereColumn("map_proyects.id", "cut_fibers.map_proyect_id")
                    ->orWhereColumn("map_proyects.id", "points.map_proyect_id");
            })
            ->where('map_links.output_id', $objectId)
            ->where('map_links.output_type', $objectModelClass);

        return datatables()->query($a->union($b))->toJson();
    }

    public function getMapLinksByObject(int $objectId, string $objectModelClass): Collection
    {
        if($objectModelClass === Box::class)
            return $this->getByBoxId($objectId);

        if($objectModelClass === BoxInput::class){
            $boxInput = BoxInput::find($objectId);

            return $this->getByBoxId($boxInput->box_id);
        }

        return $this->getModel()
                    ->where(function ($query) use($objectId, $objectModelClass){
                        $query->where([
                            ['map_links.input_id', $objectId],
                            ['map_links.input_type', $objectModelClass]
                        ]);
                    })
                    ->orWhere(function ($query) use($objectId, $objectModelClass){
                        $query->where([
                            ['map_links.output_id', $objectId],
                            ['map_links.output_type', $objectModelClass]
                        ]);
                    })
                    ->get();
    }

    public function getByBoxId($boxId): Collection
    {
        return $this->getModel()
                    ->select(
                        "map_links.*"
                    )
                    ->join("box_inputs", function($join){
                        $join->where(function($where){
                            $where->whereColumn("map_links.input_id", "box_inputs.id")
                                ->where("map_links.input_type", BoxInput::class);
                        })
                        ->orWhere(function($where){
                            $where->whereColumn("map_links.output_id", "box_inputs.id")
                                ->where("map_links.output_type", BoxInput::class);
                        });
                    })
                    ->where("box_inputs.box_id", $boxId)
                    ->groupBy(
                        "map_links.id",
                        "map_links.input_id",
                        "map_links.input_type",
                        "map_links.output_id",
                        "map_links.output_type",
                        "map_links.map_route_id",
                        "map_links.tube_id",
                        "map_links.created_by",
                        "map_links.updated_by",
                        "map_links.created_at",
                        "map_links.updated_at"
                    )
                    ->get();
    }

    public function whenBuildObjects($tables)
    {
        $string = "CASE ";

        foreach($tables as $table){
            $string = $string . "WHEN ($table->name.id is not null) THEN '$table->label' ";
        }

        return $string . "END AS type";
    }

    public function destroyByObject($object): bool
    {
        $mapLinks = $this->getMapLinksByObject($object->id, $object::class);

        $mapLinks->groupBy('map_route_id')->map(function($group) use($object){
            $mapLinkAmount = $group->count();

            if($group->isEmpty())
                return true;

            if($mapLinkAmount === 1){
                $this->delete($group->first());
                return true;
            }

            if($mapLinkAmount === 2){
                $data= $this->getDataToUpdate($group, $object);
                $this->delete($group->first());
                $this->update($group->last(), $data);

                return true;
            }

            foreach($group as $mapLink){
                $this->delete($mapLink);
            }
        });

        return true;
    }

    public function getDataToUpdate(Collection $mapLinks, $object): array
    {
        $data = $this->getDataOppositeObject($mapLinks->first(), $object);

        if($mapLinks->last()->input_id === $object->id && $mapLinks->last()->input_type === $object::class){
            return[
                "input_id"=>$data["id"],
                "input_type"=>$data["type"]
            ];
        }elseif($mapLinks->last()->output_id === $object->id && $mapLinks->last()->output_type === $object::class){
            return[
                "output_id"=>$data["id"],
                "output_type"=>$data["type"]
            ];
        }

        throw new Exception('Ningun elemento coincide con la busqueda de enlaces', 5525);
    }

    public function getDataOppositeObject(MapLink $mapLink, $object): array
    {
        if($mapLink->input_id === $object->id && $mapLink->input_type === $object::class){
            return[
                "id"=>$mapLink->output_id,
                "type"=>$mapLink->output_type
            ];
        }elseif($mapLink->output_id === $object->id && $mapLink->output_type === $object::class){
            return[
                "id"=>$mapLink->input_id,
                "type"=>$mapLink->input_type
            ];
        }

        throw new Exception('Ningun elemento coincide con la busqueda de enlaces', 5525);
    }

    public function insetObject(int $id, int $objectId, string $objectModelClass)
    {
        $mapLink = $this->find($id);

        if(empty($mapLink))
            throw new Exception('No se encontro la ruta intersectada');

        $this->create([
            "map_route_id"=>$mapLink->map_route_id,
            "input_id"=>$objectId,
            "input_type"=>$objectModelClass,
            "output_id"=>$mapLink->output_id,
            "output_type"=>$mapLink->output_type,
            "tube_id"=>$mapLink->tube_id
        ]);

        $this->update($mapLink,[
            "output_id"=>$objectId,
            "output_type"=>$objectModelClass,
        ]);
    }

    public function findByInputBox(int $boxInputId): MapLink
    {
        $objectModelClass = BoxInput::class;

        return $this->getModel()
                    ->where(function ($query) use($boxInputId, $objectModelClass){
                        $query->where([
                            ['map_links.input_id', $boxInputId],
                            ['map_links.input_type', $objectModelClass]
                        ]);
                    })
                    ->orWhere(function ($query) use($boxInputId, $objectModelClass){
                        $query->where([
                            ['map_links.output_id', $boxInputId],
                            ['map_links.output_type', $objectModelClass]
                        ]);
                    })
                    ->first();
    }

    public function findBySplitter(int $splitterId): MapLink
    {
        return $this->getModel()
                    ->select(
                        "map_links.*"
                    )
                    ->leftJoin("equipment_links", "equipment_links.map_link_id", "map_links.id")
                    ->leftJoin("ports", function($join){
                        $join->on("ports.id", "equipment_links.input_id")
                        ->orWhereColumn("ports.id", "equipment_links.output_id");
                    })
                    ->where("ports.portable_id", $splitterId)
                    ->where("ports.portable_type", Splitter::class)
                    ->first();
    }
}
?>
