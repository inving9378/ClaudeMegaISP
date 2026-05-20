<?php

namespace App\Repositories;

use App\Modules\Core\CRM\Models\CrmLeadInformation;
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
