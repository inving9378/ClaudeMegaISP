<?php

namespace App\Repositories;

use App\Models\Port;
use App\Models\Transceiver;
use App\Repositories\BaseRepository;
use Illuminate\Support\Facades\DB;

/**
 * Francisco López Zetina
 */
	class TransceiverRepository extends BaseRepository
	{
		public function getModel(): Transceiver
		{
			return new Transceiver();
		}

        public function getForDatatable(int $cardId)
        {
            $query = DB::table('transceivers')
            ->select(
                'transceivers.id',
                'transceivers.description',
                'transceivers.type',
                'ports_linked.number as port_number',
                'map_proyects.name as map_proyect_name'
            )
            ->join('map_proyects', 'map_proyects.id', 'transceivers.map_proyect_id')
            ->leftJoin('ports', function($join){
                $join->on('ports.portable_id', 'transceivers.id')
                    ->where('ports.portable_type', Transceiver::class)
                    ->where('ports.type', 'entrada');
            })
            ->leftJoin('equipment_links', function($join){
                $join->on('equipment_links.input_id', 'ports.id')
                ->orWhereColumn('equipment_links.output_id', 'ports.id');
            })
            ->leftJoin(DB::raw('ports AS ports_linked'), function ($join){
                $join
                ->where(function($query){
                    $query->whereColumn('equipment_links.input_id', 'ports_linked.id')
                        ->whereColumn('equipment_links.input_id', '<>','ports.id');
                })
                ->orWhere(function($query){
                    $query->whereColumn('equipment_links.output_id', 'ports_linked.id')
                        ->whereColumn('equipment_links.output_id', '<>','ports.id');
                });
            })
            ->where('transceivers.card_id', $cardId);

            return datatables()->query($query)->toJson();
        }
	}
?>
