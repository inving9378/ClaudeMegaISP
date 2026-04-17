<?php

namespace App\Repositories;

use App\Models\DocumentClient;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class DocumentClientRepository extends BaseRepository
	{
		public function getModel(): DocumentClient
		{
			return new DocumentClient();
		}
	}
?>
