<?php

namespace App\Repositories;

use App\Models\BoxInput;
use App\Models\Fiber;
use App\Repositories\BaseRepository;
use Illuminate\Support\Facades\DB;

/**
 * Francisco López Zetina
 */
	class FiberRepository extends BaseRepository
	{
		public function getModel(): Fiber
		{
			return new Fiber();
		}

        public function SearchForSelectByBuffer(?string $text, int $page, int $bufferId, int $mapRouteId)
        {
            $querry = $this->getModel()
                        ->select(
                            'fibers.id',
                            DB::raw("CONCAT(fibers.number, ' (', colors.name, ')' ) AS text"),
                        )
                        ->join('colors', 'colors.id', 'fibers.color_id')
                        ->join('equipment_links', 'equipment_links.fiber_id', 'fibers.id')
                        ->join('map_links', 'map_links.id', 'equipment_links.map_link_id')
                        ->where('map_links.map_route_id', $mapRouteId)
                        ->where('fibers.buffer_id', $bufferId)
                        ->groupBy(
                            'fibers.id',
                        );


            if(!empty($text)){
                $querry = $querry->where('fibers.id', 'LIKE', "%$text%");
            }

            return $querry->paginate(7, $page);
        }

        public function listByInputBox(?string $text, int $page, int $bufferId, int $boxInputId)
        {
            $querry = $this->getModel()
                        ->select(
                            'fibers.id',
                            DB::raw("CONCAT(fibers.number, ' (', colors.name, ')' ) AS text"),
                        )
                        ->join('colors', 'colors.id', 'fibers.color_id')
                        ->join('equipment_links', 'equipment_links.fiber_id', 'fibers.id')
                        ->join('map_links', 'map_links.id', 'equipment_links.map_link_id')
                        ->where(function($where) use($boxInputId){
                            $where
                            ->where(function($where) use($boxInputId){
                                $where->where("map_links.input_id", $boxInputId)
                                    ->where("map_links.input_type", BoxInput::class);
                            })
                            ->orWhere(function($where) use($boxInputId){
                                $where->where("map_links.output_id", $boxInputId)
                                    ->where("map_links.output_type", BoxInput::class);
                            });
                        })
                        ->where('fibers.buffer_id', $bufferId)
                        ->groupBy(
                            'fibers.id',
                        );


            if(!empty($text)){
                $querry = $querry->where('fibers.id', 'LIKE', "%$text%");
            }

            return $querry->paginate(7, $page);
        }
	}
?>
