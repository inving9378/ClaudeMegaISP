<?php

namespace App\Repositories;

use App\Models\PoleAccessory;
use App\Repositories\BaseRepository;
use Illuminate\Support\Facades\DB;

/**
 * Francisco López Zetina
 */
	class PoleAccessoryRepository extends BaseRepository
	{
		public function getModel(): PoleAccessory
		{
			return new PoleAccessory();
		}

        public function getByPoleIdForDatatable(int $poleId)
        {
            $query = DB::table("pole_accessories")
                        ->select(
                            "pole_accessories.*",
                            "map_proyects.name AS map_proyect_name"
                        )
                        ->join("map_proyects", "map_proyects.id", "pole_accessories.map_proyect_id")
                        ->where("pole_id", $poleId);

            return datatables()->query($query)->toJson();
        }
	}
?>
