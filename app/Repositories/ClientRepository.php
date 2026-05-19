<?php

namespace App\Repositories;

use App\Modules\Core\Clientes\Models\Client;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class ClientRepository extends BaseRepository
	{
		public function getModel(): Client
		{
			return new Client();
		}
	}
?>
