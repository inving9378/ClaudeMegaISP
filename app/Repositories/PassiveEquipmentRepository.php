<?php

namespace App\Repositories;

use App\Models\PassiveEquipment;
use App\Repositories\BaseRepository;
use App\Services\MapService;
use App\Services\SimpleService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Francisco López Zetina
 */
	class PassiveEquipmentRepository extends BaseRepository
	{
		public function getModel(): PassiveEquipment
		{
			return new PassiveEquipment();
		}

        public function getByRackIdForDataTable(int $id)
        {
            $query = $this->getModel()
                        ->select(
                            'passive_equipments.id',
                            'passive_equipments.name',
                            'passive_equipments.description',
                            'passive_equipment_types.type',
                            'brands.name as brand',
                            'passive_equipment_types.model',
                            'map_proyects.name as map_proyect_name'
                        )
                        ->join('passive_equipment_types', 'passive_equipment_types.id', 'passive_equipments.type_id')
                        ->join('map_proyects', 'map_proyects.id', 'passive_equipments.map_proyect_id')
                        ->join('brands', 'brands.id', 'passive_equipment_types.brand_id')
                        ->where('passive_equipments.rack_id', $id);

            return datatables()->eloquent($query)->toJson();
        }

        /**
        * @param string $text
        * @return LengthAwarePaginator
        */
        public function getListToSelect($text, int $page): LengthAwarePaginator
        {
            $querry = $this->getModel()
                            ->select(
                                "passive_equipments.id",
                                "passive_equipments.name AS text"
                            )
                            ->name($text)
                            ->orderBy('passive_equipments.id')
                            ->groupBy(
                                "passive_equipments.id",
                                "passive_equipments.name"
                            );

            return $querry->paginate(7, $page);
        }

        public function destroyByObject($object)
        {
            if($object::class !== Rack::class)
                return false;

            $passiveEquipments = $object->passiveEquipments;

            $passiveEquipments->map(function($passiveEquipment){
            });

            return true;
        }
	}
?>
