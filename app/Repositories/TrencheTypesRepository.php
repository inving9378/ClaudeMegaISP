<?php

namespace App\Repositories;

use App\Models\Trench;
use App\Models\TrencheTypes;
use App\Repositories\BaseRepository;
use Illuminate\Support\Facades\DB;

/**
 * Francisco López Zetina
 */
	class TrencheTypesRepository extends BaseRepository
	{
		public function getModel(): TrencheTypes
		{
			return new TrencheTypes();
		}

        public function getForDatatable()
        {
            $query = DB::table('trenche_types')
                        ->select(
                            'trenche_types.id',
                            'trenche_types.model',
                            'trenche_types.width',
                            'trenche_types.lenght',
                            'trenche_types.depth',
                            'brands.name AS brand',
                        )
                        ->join('brands', 'brands.id', 'trenche_types.brand_id');

            return datatables()->query($query)->toJson();
        }
	}
?>
