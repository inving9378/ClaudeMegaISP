<?php

namespace App\Repositories;

use App\Models\Rack;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Francisco López Zetina
 */
	class RackRepository extends BaseRepository
	{
		public function getModel(): Rack
		{
			return new Rack();
		}

        public function getBySiteId(int $siteId): Collection
        {
            return $this->getModel()->where('site_id', $siteId)->get();
        }

        public function getBySiteIdForDatatable(int $siteId)
        {
            $query = $this->getModel()
                        ->select(
                            "racks.id",
                            "racks.name",
                            "racks.number",
                            "racks.description",
                            DB::raw('COUNT(active_equipments.id) + COUNT(passive_equipments.id) AS equipment')
                        )
                        ->leftJoin('active_equipments', 'active_equipments.rack_id', 'racks.id')
                        ->leftJoin('passive_equipments', 'passive_equipments.rack_id', 'racks.id')
                        ->where('site_id', $siteId)
                        ->groupBy(
                            "racks.id",
                            "racks.name",
                            "racks.number",
                            "racks.description"
                        );

            return datatables()->eloquent($query)->toJson();
        }



        /**
        * @param string $text
        * @return LengthAwarePaginator
        */
        public function getListToSelect($text, int $page): LengthAwarePaginator
        {
            $querry = $this->getModel()
                            ->select(
                                "racks.id",
                                "racks.name AS text"
                            )
                            ->name($text)
                            ->orderBy('racks.id')
                            ->groupBy(
                                "racks.id",
                                "racks.name"
                            );

            return $querry->paginate(7, $page);
        }
	}
?>
