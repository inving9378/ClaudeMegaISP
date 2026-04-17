<?php

namespace App\Repositories;

use App\Models\ClientInternetService;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class ClientInternetServiceRepository extends BaseRepository
	{
		public function getModel(): ClientInternetService
		{
			return new ClientInternetService();
		}
	}
?>
