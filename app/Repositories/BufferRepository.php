<?php

namespace App\Repositories;

use App\Models\BoxInput;
use App\Models\Buffer;
use App\Repositories\BaseRepository;
use Illuminate\Support\Facades\DB;

/**
 * Francisco López Zetina
 */
	class BufferRepository extends BaseRepository
	{
		public function getModel(): Buffer
		{
			return new Buffer();
		}

        public function SearchForSelectByMapRoute(?string $text, int $page, int $mapRouteId)
        {
            $querry = $this->getModel()
                        ->select(
                            'buffers.id',
                            DB::raw("CONCAT(buffers.id, ' (',  colors.name, ')') AS text"),
                        )
                        ->join('colors', 'colors.id', 'buffers.color_id')
                        ->join('fibers', 'fibers.buffer_id', 'buffers.id')
                        ->join('equipment_links', 'equipment_links.fiber_id', 'fibers.id')
                        ->join('map_links', 'map_links.id', 'equipment_links.map_link_id')
                        ->where('map_links.map_route_id', $mapRouteId)
                        ->groupBy(
                            'buffers.id',
                        );

            if(!empty($text)){
                $querry = $querry->where('buffers.id', 'LIKE', "%$text%");
            }

            return $querry->paginate(7, $page);
        }

        public function listByInputBox(?string $text, int $page, int $boxInputId)
        {
            $querry = $this->getModel()
                        ->select(
                            'buffers.id',
                            DB::raw("CONCAT(buffers.id, ' (',  colors.name, ')') AS text"),
                        )
                        ->join('colors', 'colors.id', 'buffers.color_id')
                        ->join('fibers', 'fibers.buffer_id', 'buffers.id')
                        ->join('equipment_links', 'equipment_links.fiber_id', 'fibers.id')
                        ->join('map_links', 'map_links.id', 'equipment_links.map_link_id')
                        ->where(function($where) use($boxInputId){
                            $where->where("map_links.input_id", $boxInputId)
                                ->where("map_links.input_type", BoxInput::class);
                        })
                        ->orWhere(function($where) use($boxInputId){
                            $where->where("map_links.output_id", $boxInputId)
                                ->where("map_links.output_type", BoxInput::class);
                        })
                        ->groupBy(
                            'buffers.id',
                        );

            if(!empty($text)){
                $querry = $querry->where('buffers.id', 'LIKE', "%$text%");
            }

            return $querry->paginate(7, $page);
        }
	}
?>
