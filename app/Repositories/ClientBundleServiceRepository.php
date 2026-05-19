<?php

namespace App\Repositories;

use App\Modules\Core\Clientes\Models\ClientBundleService;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class ClientBundleServiceRepository extends BaseRepository
	{
		public function getModel(): ClientBundleService
		{
			return new ClientBundleService();
		}
	}
?>
