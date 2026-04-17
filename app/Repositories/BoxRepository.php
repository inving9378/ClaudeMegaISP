<?php

namespace App\Repositories;

use App\Models\Box;
use App\Repositories\BaseRepository;
use Illuminate\Support\Facades\DB;

/**
 * Francisco López Zetina
 */
	class BoxRepository extends BaseRepository
	{
		public function getModel(): Box
		{
			return new Box();
		}

        public function createComponents(Box $box)
        {
            (new BoxInputRepository())->createByBox($box);
            (new TrayRepository())->createByBox($box);
            (new PortRepository())->createByBox($box);
        }

        public function getForDatatable(int $pointId)
        {
            $query = DB::table('boxes')
                        ->select(
                            'boxes.id',
                            'boxes.nomenclature',
                            'box_types.model',
                            'box_types.type',
                            'brands.name AS brand',
                            'map_proyects.name as map_proyect_name'
                        )
                        ->join('box_types', 'box_types.id', 'boxes.box_type_id')
                        ->join('brands', 'brands.id', 'box_types.brand_id')
                        ->join('map_proyects', 'map_proyects.id', 'boxes.map_proyect_id')
                        ->where('boxes.point_id', $pointId);

            return datatables()->query($query)->toJson();
        }

        public function findByBoxInputId(int $boxInputId): Box
        {
            return $this->getModel()
                        ->select(
                            "boxes.*"
                        )
                        ->join("box_inputs", "box_inputs.box_id", "boxes.id")
                        ->where("box_inputs.id", $boxInputId)
                        ->first();
        }

        public function findBySplitter(int $splitterId): Box
        {
            return Box::select("boxes.*")
                        ->join("splitters", "splitters.box_id", "boxes.id")
                        ->where("splitters.id", $splitterId)
                        ->first();
        }

        public function findByTray(int $trayId): Box
        {
            return Box::select("boxes.*")
                        ->join("trays", "trays.box_id", "boxes.id")
                        ->where("trays.id", $trayId)
                        ->first();
        }

        public function destroyByObject($object): bool
        {
            if($object::class !== Box::class)
                return false;

            $object->trays->map(function($tray){
                $this->delete($tray);
            });

            return true;
        }
	}
?>
