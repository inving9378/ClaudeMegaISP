<?php

namespace App\Repositories;

use App\Models\PaymentPromise;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class PaymentPromiseRepository extends BaseRepository
	{
		public function getModel(): PaymentPromise
		{
			return new PaymentPromise();
		}
	}
?>
