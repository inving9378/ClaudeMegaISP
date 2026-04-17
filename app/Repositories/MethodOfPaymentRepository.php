<?php

namespace App\Repositories;

use App\Models\MethodOfPayment;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class MethodOfPaymentRepository extends BaseRepository
	{
		public function getModel(): MethodOfPayment
		{
			return new MethodOfPayment();
		}
	}
?>
