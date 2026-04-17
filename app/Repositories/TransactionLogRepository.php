<?php

namespace App\Repositories;

use App\Models\TransactionLog;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class TransactionLogRepository extends BaseRepository
	{
		public function getModel(): TransactionLog
		{
			return new TransactionLog();
		}
	}
?>
