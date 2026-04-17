<?php

namespace App\Repositories;

use App\Models\Client;
use App\Models\ClientCustomService;
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
