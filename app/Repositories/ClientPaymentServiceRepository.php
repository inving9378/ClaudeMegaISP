<?php

namespace App\Repositories;

use App\Models\ClientPaymentService;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class ClientPaymentServiceRepository extends BaseRepository
	{
		public function getModel(): ClientPaymentService
		{
			return new ClientPaymentService();
		}
	}
?>
