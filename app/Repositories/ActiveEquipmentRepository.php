<?php

namespace App\Repositories;

use App\Models\ActiveEquipment;
use App\Models\Rack;
use App\Models\Site;
use App\Repositories\BaseRepository;
use App\Services\MapService;
use App\Services\SimpleService;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Francisco López Zetina
 */
	class ActiveEquipmentRepository extends BaseRepository
	{
		public function getModel(): ActiveEquipment
		{
			return new ActiveEquipment();
		}

        public function getByRackIdForDataTable(int $id)
        {
            $query = $this->getModel()
                        ->select(
                            'active_equipments.id',
                            'active_equipments.name',
                            'active_equipments.description',
                            'active_equipments.serial_number',
                            'map_proyects.name as map_proyect_name',
                            'active_equipment_types.type',
                            'brands.name as brand',
                            'active_equipment_types.model'
                        )
                        ->join('active_equipment_types', 'active_equipment_types.id', 'active_equipments.type_id')
                        ->join('map_proyects', 'map_proyects.id', 'active_equipments.map_proyect_id')
                        ->join('brands', 'brands.id', 'active_equipment_types.brand_id')
                        ->where('active_equipments.rack_id', $id);

            return datatables()->eloquent($query)->toJson();
        }

        public function destroyByObject($object)
        {
            if($object::class !== Rack::class)
                return false;

            $activeEquipments = $object->activeEquipments;

            $activeEquipments->map(function($activeEquipment){
            });

            return true;
        }
	}
?>
