<?php

namespace App\Repositories;

use App\Modules\Core\Clientes\Models\ClientUser;
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
