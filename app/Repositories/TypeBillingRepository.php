<?php

namespace App\Repositories;

use App\Models\TypeBilling;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class TypeBillingRepository extends BaseRepository
	{
		public function getModel(): TypeBilling
		{
			return new TypeBilling();
		}
	}
?>
