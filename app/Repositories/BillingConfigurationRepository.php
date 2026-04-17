<?php

namespace App\Repositories;

use App\Models\BillingConfiguration;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class BillingConfigurationRepository extends BaseRepository
	{
		public function getModel(): BillingConfiguration
		{
			return new BillingConfiguration();
		}
	}
?>
