<?php

namespace App\Repositories;

use App\Models\CutFiber;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class CutFiberRepository extends BaseRepository
	{
		public function getModel(): CutFiber
		{
			return new CutFiber();
		}
	}
?>
