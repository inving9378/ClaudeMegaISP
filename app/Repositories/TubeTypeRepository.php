<?php

namespace App\Repositories;

use App\Models\TubeType;
use App\Repositories\BaseRepository;
use Illuminate\Support\Facades\DB;

/**
 * Francisco López Zetina
 */
	class TubeTypeRepository extends BaseRepository
	{
		public function getModel(): TubeType
		{
			return new TubeType();
		}

        public function getForDatatable()
        {
            $query = DB::table('tube_types');

            return datatables()->query($query)->toJson();
        }
	}
?>
