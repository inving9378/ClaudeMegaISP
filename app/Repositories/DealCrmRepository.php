<?php

namespace App\Repositories;

use App\Models\DealCrm;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class DealCrmRepository extends BaseRepository
	{
		public function getModel(): DealCrm
		{
			return new DealCrm();
		}
	}
?>
