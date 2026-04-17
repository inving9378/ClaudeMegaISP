<?php

namespace App\Repositories;

use App\Models\Client;
use App\Models\ClientMainInformation;
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
