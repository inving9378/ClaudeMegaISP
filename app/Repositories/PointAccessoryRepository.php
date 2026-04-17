<?php

namespace App\Repositories;

use App\Models\PointAccessory;
use App\Repositories\BaseRepository;
use Illuminate\Support\Facades\DB;

/**
 * Francisco López Zetina
 */
	class PointAccessoryRepository extends BaseRepository
	{
		public function getModel(): PointAccessory
		{
			return new PointAccessory();
		}

        public function getByPointIdForDatatable(int $pointId)
        {
            $query = DB::table("point_accessories")
                        ->select(
                            "point_accessories.*",
                            "map_proyects.name AS map_proyect_name"
                        )
                        ->join("map_proyects", "map_proyects.id", "point_accessories.map_proyect_id")
                        ->where("point_id", $pointId);

            return datatables()->query($query)->toJson();
        }
	}
?>
