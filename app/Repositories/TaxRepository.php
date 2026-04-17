<?php

namespace App\Repositories;

use App\Models\Tax;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class TaxRepository extends BaseRepository
	{
		public function getModel(): Tax
		{
			return new Tax();
		}
	}
?>
