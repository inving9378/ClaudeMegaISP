<?php

namespace App\Repositories;

use App\Models\MikrotikTariffTargetTail;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class MikrotikTariffTargetTailRepository extends BaseRepository
	{
		public function getModel(): MikrotikTariffTargetTail
		{
			return new MikrotikTariffTargetTail();
		}
	}
?>
