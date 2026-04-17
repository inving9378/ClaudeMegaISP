<?php

namespace App\Repositories;

use App\Models\PaymentAccount;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class PaymentAccountRepository extends BaseRepository
	{
		public function getModel(): PaymentAccount
		{
			return new PaymentAccount();
		}
	}
?>
