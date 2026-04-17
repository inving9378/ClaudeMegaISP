<?php

namespace App\Repositories;

use App\Models\Color;
use App\Repositories\BaseRepository;

/**
 * Francisco López Zetina
 */
	class ColorRepository extends BaseRepository
	{
		public function getModel(): Color
		{
			return new Color();
		}

        public function findByCode(string $code) : Color
        {
            return $this->getModel()->where("code", $code)->first();
        }
	}
?>
