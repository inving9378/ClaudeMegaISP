<?php

namespace App\Repositories;

use App\Models\PassiveEquipmentType;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class PassiveEquipmentTypeRepository extends BaseRepository
	{
		public function getModel(): PassiveEquipmentType
		{
			return new PassiveEquipmentType();
		}

        public function getForDatatable()
        {
            $query = $this->getModel()
                        ->select(
                            "passive_equipment_types.id",
                            "passive_equipment_types.model",
                            "brands.name as brand",
                            "passive_equipment_types.type",
                            "passive_equipment_types.ports",
                            "passive_equipment_types.trays",
                        )
                        ->leftJoin('brands', 'brands.id', 'passive_equipment_types.brand_id');

            return datatables()->eloquent($query)->toJson();
        }

        public function getOrCreate(string $type, string $brand, string $model, int $ports, int $trays): ?PassiveEquipmentType
        {
            $object = $this->getByData($type, $brand, $model, $ports, $trays);

            if(!empty($object))
                return $object;

            return $this->create([
                "type" => $type,
                "brand" => $brand,
                "model" => $model,
                "ports" => $ports,
                "trays" => $trays
            ]);
        }

        public function getByData(string $type, string $brand, string $model, int $ports, int $trays): ?PassiveEquipmentType
        {
            return $this->getModel()
                        ->where([
                            ["type", $type],
                            ["brand", $brand],
                            ["model", $model],
                            ["ports", $ports],
                            ["trays", $trays]
                        ])
                        ->first();
        }
	}
?>
