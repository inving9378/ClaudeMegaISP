<?php

namespace App\Repositories;

use App\Models\QuoteCrm;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class QuoteCrmRepository extends BaseRepository
	{
		public function getModel(): QuoteCrm
		{
			return new QuoteCrm();
		}
	}
?>
