<?php

namespace App\Repositories;

use App\Models\Receipt;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class ReceiptRepository extends BaseRepository
	{
		public function getModel(): Receipt
		{
			return new Receipt();
		}
	}
?>
