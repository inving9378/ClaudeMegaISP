<?php

namespace App\Repositories;

use App\Models\Pole;
use App\Models\Site;
use App\Repositories\BaseRepository;
use Illuminate\Support\Facades\DB;

/**
 * Francisco López Zetina
 */
	class PoleRepository extends BaseRepository
	{
		public function getModel(): Pole
		{
			return new Pole();
		}

        public function getBySiteIdForDatatable(int $siteId)
        {
            $query = $this->getModel()
                        ->select(
                            "poles.id",
                            "poles.description",
                            "poles.height",
                            "poles.type",
                            "poles.tension"
                        )
                        ->leftJoin('map_links', function ($join) {
                            $join->on('map_links.output_id', '=', 'poles.id')
                                ->where('map_links.output_type', Pole::class)
                                ->where('map_links.input_type', Site::class);
                        })
                        ->where('map_links.input_id', $siteId)
                        ->groupBy(
                            "poles.id",
                            "poles.description",
                            "poles.height",
                            "poles.type",
                            "poles.tension"
                        );

            return datatables()->eloquent($query)->toJson();
        }
	}
?>
