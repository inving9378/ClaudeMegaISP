<?php

namespace App\Repositories;

use App\Models\TypeBilling;
use App\Models\User;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class UserRepository extends BaseRepository
	{
		public function getModel(): User
		{
			return new User();
		}
	}
?>
