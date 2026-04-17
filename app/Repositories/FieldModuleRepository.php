<?php

namespace App\Repositories;

use App\Models\FieldModule;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class FieldModuleRepository extends BaseRepository
	{
		public function getModel(): FieldModule
		{
			return new FieldModule();
		}
	}
?>
