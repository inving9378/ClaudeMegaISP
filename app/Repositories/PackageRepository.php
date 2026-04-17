<?php

namespace App\Repositories;

use App\Models\NetworkIp;
use App\Models\Package;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class PackageRepository extends BaseRepository
	{
		public function getModel(): Package
		{
			return new Package();
		}
	}
?>
