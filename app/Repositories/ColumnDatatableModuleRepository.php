<?php

namespace App\Repositories;

use App\Models\ColumnDatatableModule;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class ColumnDatatableModuleRepository extends BaseRepository
	{
		public function getModel(): ColumnDatatableModule
		{
			return new ColumnDatatableModule();
		}
	}
?>
