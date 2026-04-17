<?php

namespace App\Repositories;

use App\Models\Box;
use App\Models\Port;
use App\Models\Tray;
use App\Repositories\BaseRepository;
use Illuminate\Support\Facades\DB;

/**
 * Francisco López Zetina
 */
	class TrayRepository extends BaseRepository
	{
		public function getModel(): Tray
		{
			return new Tray();
		}

        public function getForDatatable(int $boxId)
        {
            $query = DB::table("trays")
                        ->where("box_id", $boxId);

            return datatables()->query($query)->toJson();
        }

        public function listByBox(int $boxId, ?string $text, int $page)
        {
            $querry =  DB::table('trays')
                        ->select(
                            "trays.id",
                            "trays.number AS text"
                        )
                        ->where('trays.box_id', $boxId)
                        ->orderBy("trays.number");

            if(!empty($text)){
                $querry = $querry->where('trays.number', 'LIKE', "%$text%");
            }

            return $querry->paginate(7, $page);
        }

        public function createByBox(Box $box)
        {
            $portRepository = new PortRepository();
            for ($i = 1 ; $i <= $box->type->trays ; $i++) {
                $tray = $this->create([
                    'number'=>$i,
                    'box_id'=>$box->id
                ]);

                for ($h = 1 ; $h <= $box->type->mergers_by_tray ; $h++) {
                    $portRepository->create([
                        'number'=>$h,
                        'type'=>Port::$fusion,
                        'portable_id'=>$tray->id,
                        'portable_type'=>$tray::class,
                    ]);
                }
            }
        }
	}
?>
