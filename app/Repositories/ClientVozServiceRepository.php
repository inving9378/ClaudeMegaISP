<?php

namespace App\Repositories;

use App\Models\ClientVozService;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class ClientVozServiceRepository extends BaseRepository
	{
		public function getModel(): ClientVozService
		{
			return new ClientVozService();
		}
	}
?>
