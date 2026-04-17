<?php

namespace App\Repositories;

use App\Models\Site;
use App\Repositories\BaseRepository;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Francisco López Zetina
 */
	class SiteRepository extends BaseRepository
	{
		public function getModel(): Site
		{
			return new Site();
		}

        /**
        * @param string $text
        * @return LengthAwarePaginator
        */
        public function getListToSelect($text, int $page): LengthAwarePaginator
        {
            $querry = $this->getModel()
                            ->select(
                                "sites.id",
                                "sites.name AS text"
                            )
                            ->name($text)
                            ->orderBy('sites.id')
                            ->groupBy(
                                "sites.id",
                                "sites.name"
                            );

            return $querry->paginate(7, $page);
        }

        public function findByEquipment(int $passiveEquipmentId,string $equipmentTable) : Site
        {
            return Site::select("sites.*")
                        ->join("racks", "racks.site_id", "sites.id")
                        ->join($equipmentTable, "$equipmentTable.rack_id", "racks.id")
                        ->where("$equipmentTable.id", $passiveEquipmentId)
                        ->first();
        }
	}
?>
