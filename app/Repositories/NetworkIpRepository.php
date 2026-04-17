<?php

namespace App\Repositories;

use App\Models\NetworkIp;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class NetworkIpRepository extends BaseRepository
	{
		public function getModel(): NetworkIp
		{
			return new NetworkIp();
		}
	}
?>
