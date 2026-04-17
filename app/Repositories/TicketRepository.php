<?php

namespace App\Repositories;

use App\Models\Ticket;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class TicketRepository extends BaseRepository
	{
		public function getModel(): Ticket
		{
			return new Ticket();
		}
	}
?>
