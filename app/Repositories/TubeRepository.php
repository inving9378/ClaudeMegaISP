<?php

namespace App\Repositories;

use App\Models\Tube;
use App\Repositories\BaseRepository;
use Illuminate\Support\Facades\DB;

/**
 * Francisco López Zetina
 */
	class TubeRepository extends BaseRepository
	{
		public function getModel(): Tube
		{
			return new Tube();
		}

        public function getForDatatable()
        {
            $query = DB::table('tubes')
                        ->select(
                            'tubes.id',
                            'tube_types.type',
                            'tube_types.diameter'
                        )
                        ->join('tube_types', 'tube_types.id', 'tubes.tube_type_id');

            return datatables()->query($query)->toJson();
        }
	}
?>
