<?php

namespace App\Repositories;

use App\Models\MikrotikItemToExcecuteAction;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class MikrotikItemToExcecuteActionRepository extends BaseRepository
	{
		public function getModel(): MikrotikItemToExcecuteAction
		{
			return new MikrotikItemToExcecuteAction();
		}
	}
?>
