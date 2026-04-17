<?php

namespace App\Repositories;

use App\Models\Trench;
use App\Repositories\BaseRepository;
use Illuminate\Support\Facades\DB;

/**
 * Francisco López Zetina
 */
	class TrenchRepository extends BaseRepository
	{
		public function getModel(): Trench
		{
			return new Trench();
		}

        public function getForDatatable()
        {
            $query = DB::table('trenches')
                        ->select(
                            'trenches.id',
                            'trenches.name',
                            'trenche_types.width',
                            'trenche_types.lenght',
                            'trenche_types.depth',
                            'brands.name AS brand',
                        )
                        ->join('trenche_types', 'trenche_types.id', 'trenches.trenche_type_id')
                        ->join('brands', 'brands.id', 'trenche_types.brand_id');

            return datatables()->query($query)->toJson();
        }
	}
?>
