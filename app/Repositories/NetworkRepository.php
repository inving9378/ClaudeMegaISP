<?php

namespace App\Repositories;

use App\Models\Network;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class NetworkRepository extends BaseRepository
	{
		public function getModel(): Network
		{
			return new Network();
		}
	}
?>
