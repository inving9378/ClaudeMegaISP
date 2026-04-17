<?php

namespace App\Repositories;

use App\Models\File;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class FileRepository extends BaseRepository
	{
		public function getModel(): File
		{
			return new File();
		}
	}
?>
