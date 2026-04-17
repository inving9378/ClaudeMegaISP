<?php

namespace App\Repositories;

use App\Models\prepaid_period;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class PrepaidPeriodRepository extends BaseRepository
	{
		public function getModel(): prepaid_period
		{
			return new prepaid_period();
		}
	}
?>
