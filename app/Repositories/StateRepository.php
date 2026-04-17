<?php

namespace App\Repositories;

use App\Models\State;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class StateRepository extends BaseRepository
	{
		public function getModel(): State
		{
			return new State();
		}
	}
?>
