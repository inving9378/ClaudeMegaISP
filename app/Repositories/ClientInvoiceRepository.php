<?php

namespace App\Repositories;

use App\Modules\Core\Clientes\Models\ClientInvoice;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class ClientInvoiceRepository extends BaseRepository
	{
		public function getModel(): ClientInvoice
		{
			return new ClientInvoice();
		}
	}
?>
