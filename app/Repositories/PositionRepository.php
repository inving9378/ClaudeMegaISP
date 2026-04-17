<?php

namespace App\Repositories;

use App\Models\Box;
use App\Models\CutFiber;
use App\Models\Point;
use App\Models\Pole;
use App\Models\Position;
use App\Models\Site;
use App\Models\Trench;
use App\Repositories\BaseRepository;
use Illuminate\Support\Facades\DB;

/**
 * Francisco López Zetina
 */
	class PositionRepository extends BaseRepository
	{
		public function getModel(): Position
		{
			return new Position();
		}

        public function getObjects(int $mapProyectId)
        {
            return DB::table('positions')
                    ->select(
                        "positions.id",
                        DB::raw("
                            CASE
                                WHEN (sites.id is not null) THEN
                                    CONCAT(sites.id, '-', 'site')
                                WHEN (boxes.id is not null) THEN
                                    CONCAT(boxes.id, '-', 'box')
                                WHEN (poles.id is not null) THEN
                                    CONCAT(poles.id, '-', 'pole')
                                WHEN (cut_fibers.id is not null) THEN
                                    CONCAT(cut_fibers.id, '-', 'cut_fiber')
                                WHEN (points.id is not null) THEN
                                    CONCAT(points.id, '-', 'point')
                                WHEN (trenches.id is not null) THEN
                                    CONCAT(trenches.id, '-', 'trench')
                                ELSE
                                    null
                            END AS objectId
                        "),
                        DB::raw("
                            CASE
                                WHEN (sites.id is not null) THEN
                                    'site'
                                WHEN (boxes.id is not null) THEN
                                    box_types.type
                                WHEN (poles.id is not null) THEN
                                    poles.type
                                WHEN (cut_fibers.id is not null) THEN
                                    'cut_fiber'
                                WHEN (points.id is not null) THEN
                                    'point'
                                WHEN (trenches.id is not null) THEN
                                    'trench'
                                ELSE
                                    null
                            END AS type
                        "),
                        DB::raw("
                            CASE
                                WHEN (sites.id is not null) THEN
                                    sites.name
                                WHEN (boxes.id is not null) THEN
                                    boxes.nomenclature
                                WHEN (poles.id is not null) THEN
                                    poles.description
                                WHEN (cut_fibers.id is not null) THEN
                                    cut_fibers.description
                                WHEN (points.id is not null) THEN
                                    points.id
                                WHEN (trenches.id is not null) THEN
                                    trenches.id
                                ELSE
                                    null
                            END AS title
                        "),
                        DB::raw("ST_X(positions.point) AS longitude"),
                        DB::raw("ST_Y(positions.point) AS latitude")
                    )
                    ->leftJoin('sites', function ($join) use($mapProyectId) {
                        $join->on('sites.id', '=', 'positions.positionable_id')
                            ->where('sites.map_proyect_id', $mapProyectId)
                            ->where('positions.positionable_type', Site::class);
                    })
                    ->leftJoin('boxes', function ($join) use($mapProyectId) {
                        $join->on('boxes.id', '=', 'positions.positionable_id')
                            ->where('boxes.map_proyect_id', $mapProyectId)
                            ->where('positions.positionable_type', Box::class);
                    })
                    ->leftJoin('box_types', 'box_types.id', 'boxes.box_type_id')
                    ->leftJoin('poles', function ($join) use($mapProyectId) {
                        $join->on('poles.id', '=', 'positions.positionable_id')
                            ->where('poles.map_proyect_id', $mapProyectId)
                            ->where('positions.positionable_type', Pole::class);
                    })
                    ->leftJoin('cut_fibers', function ($join) use($mapProyectId) {
                        $join->on('cut_fibers.id', '=', 'positions.positionable_id')
                            ->where('cut_fibers.map_proyect_id', $mapProyectId)
                            ->where('positions.positionable_type', CutFiber::class);
                    })
                    ->leftJoin('points', function ($join) use($mapProyectId) {
                        $join->on('points.id', '=', 'positions.positionable_id')
                            ->where('points.map_proyect_id', $mapProyectId)
                            ->where('positions.positionable_type', Point::class);
                    })
                    ->leftJoin('trenches', function ($join) use($mapProyectId) {
                        $join->on('trenches.id', '=', 'positions.positionable_id')
                            ->where('trenches.map_proyect_id', $mapProyectId)
                            ->where('positions.positionable_type', Trench::class);
                    })
                    ->havingRaw('objectId is not null')
                    ->get();
        }

        public function distanceBetweenTwoPoints(int $positionFirstId, int $positionSecondId) : float
        {
            $query = DB::table("positions")
                        ->select(
                            DB::raw("
                                ST_Distance_Sphere(
                                    (SELECT
                                        point
                                    FROM
                                        positions
                                    WHERE
                                        id = $positionFirstId),
                                    (SELECT
                                        point
                                    FROM
                                        positions
                                    WHERE
                                        id = $positionSecondId)
                                ) as distances
                            ")
                        )
                        ->first();

            return $query->distances;
        }

        /**
         * Buscar por coordenadas el objeto segun el repositorio
         * @param float $longitude
         * @param float $latitude
         */
        public function getObject(float $longitude, float $latitude, int $mapProyectId)
        {
            $positionRepository = new PositionRepository();

            $position = $positionRepository->getByPointMapProyectId($longitude, $latitude, $mapProyectId);

            if(!empty($position))
                return $position->positionable;

            return null;
        }

        /**
         * Buscar por coordenadas el objeto segun el repositorio
         * @param float $longitude
         * @param float $latitude
         */
        public function getByPointMapProyectId(float $longitude, float $latitude, int $mapProyectId): ?Position
        {
            $tableRepository = new TableRepository();
            $positionableTables = $tableRepository->getHasPosition(true);

            $query = $this->getModel()
                        ->select('positions.*')
                        ->where([
                            [DB::raw("ST_X(point)"), $longitude],
                            [DB::raw("ST_y(point)"), $latitude]
                        ]);

            foreach($positionableTables as $table){
                $query->leftJoin($table->name, function ($join) use($mapProyectId, $table) {
                    $join->on("$table->name.id", '=', 'positions.positionable_id')
                        ->where("$table->name.map_proyect_id", $mapProyectId)
                        ->where('positions.positionable_type', $table->model_class);
                });
            }

            return $query->first();
        }

        /**
         * Buscar por coordenadas el objeto segun el repositorio
         * @param float $longitude
         * @param float $latitude
         */
        public function getByObjectIdAndType(int $objectId, string $type)
        {
            return $this->getModel()->where('positionable_id', $objectId)->where('positionable_type', $type)->first();
        }

        public function destroyByObject($object): bool
        {
            $position = $object->position;

            if(empty($position))
                return false;

            $this->delete($position);

            return true;
        }
	}
?>
