<?php

namespace App\Repositories;

use App\Models\TicketThread;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class TicketThreadRepository extends BaseRepository
	{
		public function getModel(): TicketThread
		{
			return new TicketThread();
		}
	}
?>
