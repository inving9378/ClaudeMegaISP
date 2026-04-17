<?php

namespace App\Repositories;

use App\Models\ClientBundleService;
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
