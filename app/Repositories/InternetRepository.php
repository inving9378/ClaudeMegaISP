<?php

namespace App\Repositories;

use App\Models\Internet;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class InternetRepository extends BaseRepository
	{
		public function getModel(): Internet
		{
			return new Internet();
		}
	}
?>
