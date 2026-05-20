<?php

namespace App\Repositories;

use App\Modules\Core\Clientes\Models\Client;
use App\Modules\Core\Clientes\Models\ClientMainInformation;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class ClientMainInformationRepository extends BaseRepository
	{
		public function getModel(): ClientMainInformation
		{
			return new ClientMainInformation();
		}
	}
?>
