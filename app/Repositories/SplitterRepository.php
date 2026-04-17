<?php

namespace App\Repositories;

use App\Models\Splitter;
use App\Models\Tray;
use App\Repositories\BaseRepository;
use Illuminate\Support\Facades\DB;

/**
 * Francisco López Zetina
 */
	class SplitterRepository extends BaseRepository
	{
		public function getModel(): Splitter
		{
			return new Splitter();
		}

        public function getForDatatable(int $boxId)
        {
            $query = DB::table("splitters")
                        ->where("box_id", $boxId);

            return datatables()->query($query)->toJson();
        }

        function ListByFusionPort(int $portId, ?string $text, int $page)
        {
            $querry =  DB::table('splitters')
                        ->select(
                            "splitters.id",
                            "splitters.number AS text"
                        )
                        ->join("boxes", "boxes.id", "splitters.box_id")
                        ->join("trays", "trays.box_id", "boxes.id")
                        ->join("ports", function($join){
                            $join->on("ports.portable_id", "trays.id")
                                ->where("ports.portable_type", Tray::class);
                        })
                        ->where('ports.id', $portId)
                        ->orderBy("splitters.number");

            if(!empty($text)){
                $querry = $querry->where('splitters.number', 'LIKE', "%$text%");
            }

            return $querry->paginate(7, $page);
        }

	}
?>
