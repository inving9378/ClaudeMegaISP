<?php

namespace App\Repositories;

use App\Models\BoxType;
use App\Repositories\BaseRepository;
use Illuminate\Support\Facades\DB;

/**
 * Francisco López Zetina
 */
	class BoxTypeRepository extends BaseRepository
	{
		public function getModel(): BoxType
		{
			return new BoxType();
		}

        public function getForDatatable()
        {
            $query = DB::table("box_types")
                        ->select(
                            "box_types.id",
                            "box_types.model",
                            "box_types.type",
                            "box_types.inputs",
                            "box_types.trays",
                            "box_types.mergers_by_tray",
                            "box_types.ports",
                            "brands.name AS brand",
                        )
                        ->join("brands", "brands.id", "box_types.brand_id");

            return datatables()->query($query)->toJson();
        }
	}
?>
