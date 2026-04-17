<?php

namespace App\Repositories;

use App\Models\Module;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class ModuleRepository extends BaseRepository
	{
		public function getModel(): Module
		{
			return new Module();
		}
	}
?>
