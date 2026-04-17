<?php

namespace App\Repositories;

use App\Models\ClientAdditionalInformation;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class ClientAdditionalInformationRepository extends BaseRepository
	{
		public function getModel(): ClientAdditionalInformation
		{
			return new ClientAdditionalInformation();
		}
	}
?>
