<?php

namespace App\Repositories;

use App\Models\ActiveEquipmentPeripheral;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class ActiveEquipmentPeripheralRepository extends BaseRepository
	{
		public function getModel(): ActiveEquipmentPeripheral
		{
			return new ActiveEquipmentPeripheral();
		}
	}
?>
