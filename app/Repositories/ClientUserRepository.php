<?php

namespace App\Repositories;

use App\Models\ClientUser;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class ClientUserRepository extends BaseRepository
	{
		public function getModel(): ClientUser
		{
			return new ClientUser();
		}
	}
?>
