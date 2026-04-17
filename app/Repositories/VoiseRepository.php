<?php

namespace App\Repositories;

use App\Models\Voise;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class VoiseRepository extends BaseRepository
	{
		public function getModel(): Voise
		{
			return new Voise();
		}
	}
?>
