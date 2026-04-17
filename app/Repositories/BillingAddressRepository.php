<?php

namespace App\Repositories;

use App\Models\BillingAddress;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class BillingAddressRepository extends BaseRepository
	{
		public function getModel(): BillingAddress
		{
			return new BillingAddress();
		}
	}
?>
