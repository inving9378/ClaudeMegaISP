<?php

namespace App\Repositories;

use App\Models\LogActivity;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class LogActivityRepository extends BaseRepository
	{
		public function getModel(): LogActivity
		{
			return new LogActivity();
		}
	}
?>
