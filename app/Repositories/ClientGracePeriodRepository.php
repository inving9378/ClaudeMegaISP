<?php

namespace App\Repositories;

use App\Modules\Core\Clientes\Models\ClientGracePeriod;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class ClientGracePeriodRepository extends BaseRepository
	{
		public function getModel(): ClientGracePeriod
		{
			return new ClientGracePeriod();
		}
	}
?>
