<?php

namespace App\Repositories;

use App\Models\Ift;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class IftRepository extends BaseRepository
	{
		public function getModel(): Ift
		{
			return new Ift();
		}
	}
?>
