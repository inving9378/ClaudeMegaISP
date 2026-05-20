<?php

namespace App\Repositories;

use App\Modules\Core\Clientes\Models\ClientInvoiceService;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class ClientInvoiceServiceRepository extends BaseRepository
	{
		public function getModel(): ClientInvoiceService
		{
			return new ClientInvoiceService();
		}
	}
?>
