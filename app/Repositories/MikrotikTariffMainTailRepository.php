<?php

namespace App\Repositories;

use App\Models\MikrotikTariffMainTail;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class MikrotikTariffMainTailRepository extends BaseRepository
	{
		public function getModel(): MikrotikTariffMainTail
		{
			return new MikrotikTariffMainTail();
		}
	}
?>
