<?php

namespace App\Repositories;

use App\Models\SystemUser;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class SystemUserRepository extends BaseRepository
	{
		public function getModel(): SystemUser
		{
			return new SystemUser();
		}
	}
?>
