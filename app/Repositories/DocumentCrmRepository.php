<?php

namespace App\Repositories;

use App\Models\DocumentCrm;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class DocumentCrmRepository extends BaseRepository
	{
		public function getModel(): DocumentCrm
		{
			return new DocumentCrm();
		}
	}
?>
