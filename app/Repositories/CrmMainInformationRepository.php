<?php

namespace App\Repositories;

use App\Modules\Core\CRM\Models\CrmMainInformation;
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
