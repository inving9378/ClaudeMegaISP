<?php

namespace App\Repositories;

use App\Models\CrmLeadInformation;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class CrmLeadInformationRepository extends BaseRepository
	{
		public function getModel(): CrmLeadInformation
		{
			return new CrmLeadInformation();
		}
	}
?>
