<?php

namespace App\Repositories;

use App\Models\Mikrotik;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class MikrotikRepository extends BaseRepository
	{
		public function getModel(): Mikrotik
		{
			return new Mikrotik();
		}
	}
?>
