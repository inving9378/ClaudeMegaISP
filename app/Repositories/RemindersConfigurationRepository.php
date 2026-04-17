<?php

namespace App\Repositories;

use App\Models\Receipt;
use App\Models\RemindersConfiguration;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class RemindersConfigurationRepository extends BaseRepository
	{
		public function getModel(): RemindersConfiguration
		{
			return new RemindersConfiguration();
		}
	}
?>
