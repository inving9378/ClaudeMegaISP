<?php

namespace App\Repositories;

use App\Models\Balance;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class BalanceRepository extends BaseRepository
	{
		public function getModel(): Balance
		{
			return new Balance();
		}
	}
?>
