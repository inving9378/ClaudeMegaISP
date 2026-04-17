<?php

namespace App\Repositories;

use App\Models\Partner;
use App\Models\Payment;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class PaymentRepository extends BaseRepository
	{
		public function getModel(): Payment
		{
			return new Payment();
		}
	}
?>
