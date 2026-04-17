<?php

namespace App\Repositories;

use App\Models\Crm;
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
