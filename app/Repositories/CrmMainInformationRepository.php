<?php

namespace App\Repositories;

use App\Models\CrmMainInformation;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class CrmMainInformationRepository extends BaseRepository
	{
		public function getModel(): CrmMainInformation
		{
			return new CrmMainInformation();
		}
	}
?>
