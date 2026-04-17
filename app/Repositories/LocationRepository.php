<?php

namespace App\Repositories;

use App\Models\Location;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class LocationRepository extends BaseRepository
	{
		public function getModel(): Location
		{
			return new Location();
		}
	}
?>
