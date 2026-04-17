<?php

namespace App\Repositories;

use App\Models\Municipality;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class MunicipalityRepository extends BaseRepository
	{
		public function getModel(): Municipality
		{
			return new Municipality();
		}
	}
?>
