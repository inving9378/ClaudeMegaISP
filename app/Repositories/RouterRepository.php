<?php

namespace App\Repositories;

use App\Models\Router;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class RouterRepository extends BaseRepository
	{
		public function getModel(): Router
		{
			return new Router();
		}
	}
?>
