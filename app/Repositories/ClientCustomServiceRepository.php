<?php

namespace App\Repositories;

use App\Modules\Core\Clientes\Models\Client;
use App\Modules\Core\Clientes\Models\ClientCustomService;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class ClientCustomServiceRepository extends BaseRepository
	{
		public function getModel(): ClientCustomService
		{
			return new ClientCustomService();
		}
	}
?>
