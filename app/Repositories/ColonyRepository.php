<?php

namespace App\Repositories;

use App\Models\Colony;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class ColonyRepository extends BaseRepository
	{
		public function getModel(): Colony
		{
			return new Colony();
		}
	}
?>
