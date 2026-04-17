<?php

namespace App\Repositories;

use App\Models\Point as ModelPoint;
use App\Repositories\BaseRepository;
use Exception;
use MatanYadaev\EloquentSpatial\Objects\Point;

    /**
    * Francisco López Zetina
    */
	class PointRepository extends BaseRepository
	{
		public function getModel(): ModelPoint
		{
			return new ModelPoint();
		}

        public function getOrCreateBy(int $mapProyectId, float $longitude, float $latitude)
        {
            $positionRepository = new PositionRepository();

            $position = $positionRepository->getByPointMapProyectId($longitude, $latitude, $mapProyectId);

            if(!empty($position))
                return $position->positionable;

            $point = $this->create([
                "map_proyect_id" => $mapProyectId
            ]);

            $positionRepository->create([
                "point"=>new Point($latitude, $longitude),
                "positionable_id"=>$point->id,
                "positionable_type"=> $this->getModel()::class,
            ]);

            return $point;
        }
	}
?>
