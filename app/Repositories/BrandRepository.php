<?php

namespace App\Repositories;

use App\Models\Brand;
use App\Repositories\BaseRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Francisco López Zetina
 */
	class BrandRepository extends BaseRepository
	{
		public function getModel(): Brand
		{
			return new Brand();
		}

        public function getOrCreate(string $name)
        {
            $object = $this->getByName($name);

            if(!empty($object))
                return $object;

            return $this->create([
                "name" => $name
            ]);
        }

        public function getForDatatable()
        {
            return datatables()->eloquent($this->getModel()->query())->toJson();
        }

        /**
        * @param string $text
        * @return LengthAwarePaginator
        */
        public function getListToSelect($text, int $page): LengthAwarePaginator
        {
            $querry = $this->getModel()
                            ->select(
                                "brands.id",
                                "brands.name AS text"
                            )
                            ->name($text)
                            ->orderBy('brands.name')
                            ->groupBy(
                                "brands.id",
                                "brands.name"
                            );

            return $querry->paginate(7, $page);
        }
	}
?>
