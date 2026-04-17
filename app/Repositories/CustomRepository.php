<?php

namespace App\Repositories;

use App\Models\Custom;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class CustomRepository extends BaseRepository
	{
		public function getModel(): Custom
		{
			return new Custom();
		}
	}
?>
