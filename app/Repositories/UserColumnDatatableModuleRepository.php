<?php

namespace App\Repositories;

use App\Models\UserColumnDatatableModule;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class UserColumnDatatableModuleRepository extends BaseRepository
	{
		public function getModel(): UserColumnDatatableModule
		{
			return new UserColumnDatatableModule();
		}
	}
?>
