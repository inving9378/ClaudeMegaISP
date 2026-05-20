<?php

namespace App\Repositories;

use App\Modules\Core\CRM\Models\Crm;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class CrmRepository extends BaseRepository
	{
		public function getModel(): Crm
		{
			return new Crm();
		}
	}
?>
