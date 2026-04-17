<?php

namespace App\Repositories;

use App\Models\Raqueta;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class RaquetaRepository extends BaseRepository
	{
		public function getModel(): Raqueta
		{
			return new Raqueta();
		}
	}
?>
