<?php

namespace App\Repositories;

use App\Models\ActiveEquipmentType;
use App\Repositories\BaseRepository;
use Illuminate\Support\Facades\DB;

/**
 * Francisco López Zetina
 */
	class ActiveEquipmentTypeRepository extends BaseRepository
	{
		public function getModel(): ActiveEquipmentType
		{
			return new ActiveEquipmentType();
		}

        public function getForDatatable()
        {
            $query = $this->getModel()
                        ->select(
                            "active_equipment_types.id",
                            "active_equipment_types.model",
                            "brands.name as brand",
                            "active_equipment_types.type",
                            "active_equipment_types.cards",
                            "active_equipment_types.ethernet_ports",
                            "active_equipment_types.sfp_ports",
                            "active_equipment_types.sfp_plus_ports"
                        )
                        ->leftJoin('brands', 'brands.id', 'active_equipment_types.brand_id');

            return datatables()->eloquent($query)->toJson();
        }

        public function getOrCreate(string $type, string $brand, string $model, int $cards, int $ethernetPorts, int $sfpPorts, int $sfpPlusPorts): ?ActiveEquipmentType
        {
            $object = $this->getByData($type, $brand, $model, $cards, $ethernetPorts, $sfpPorts, $sfpPlusPorts);

            if(!empty($object))
                return $object;

            return $this->create([
                "type" => $type,
                "brand" => $brand,
                "model" => $model,
                "cards" => $cards,
                "ethernet_ports" => $ethernetPorts,
                "sfp_ports" => $sfpPorts,
                "sfp_plus_ports" => $sfpPlusPorts
            ]);
        }

        public function getByData(string $type, string $brand, string $model, int $cards, int $ethernetPorts, int $sfpPorts, int $sfpPlusPorts): ?ActiveEquipmentType
        {
            return $this->getModel()
                        ->where([
                            ["type", $type],
                            ["brand", $brand],
                            ["model", $model],
                            ["cards", $cards],
                            ["ethernet_ports", $ethernetPorts],
                            ["sfp_ports", $sfpPorts],
                            ["sfp_plus_ports", $sfpPlusPorts],
                        ])
                        ->first();
        }
	}
?>
