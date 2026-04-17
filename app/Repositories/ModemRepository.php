<?php

namespace App\Repositories;

use App\Models\Modem;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class ModemRepository extends BaseRepository
	{
		public function getModel(): Modem
		{
			return new Modem();
		}
	}
?>
