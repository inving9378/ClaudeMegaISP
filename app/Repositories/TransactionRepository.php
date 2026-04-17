<?php

namespace App\Repositories;

use App\Models\Transaction;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class TransactionRepository extends BaseRepository
	{
		public function getModel(): Transaction
		{
			return new Transaction();
		}
	}
?>
