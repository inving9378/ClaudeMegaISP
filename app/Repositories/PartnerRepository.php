<?php

namespace App\Repositories;

use App\Models\Partner;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class PartnerRepository extends BaseRepository
	{
		public function getModel(): Partner
		{
			return new Partner();
		}
	}
?>
