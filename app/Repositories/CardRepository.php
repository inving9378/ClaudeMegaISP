<?php

namespace App\Repositories;

use App\Models\ActiveEquipment;
use App\Models\Card;
use App\Repositories\BaseRepository;
use Illuminate\Support\Facades\DB;

/**
 * Francisco López Zetina
 */
	class CardRepository extends BaseRepository
	{
		public function getModel(): Card
		{
			return new Card();
		}

        public function getByRackIdForDataTable(int $id)
        {
            $query = $this->getModel()
                        ->select(
                            'cards.id',
                            'cards.name',
                            DB::raw('COUNT(ports.id) as ports'),
                            'map_proyects.name AS proyect_name'
                        )
                        ->leftJoin('ports', function ($join) use ($id) {
                            $join->on('ports.portable_id', '=', 'cards.id')
                                ->where('ports.portable_type', Card::class);
                        })
                        ->join('map_proyects', 'map_proyects.id', 'cards.map_proyect_id')
                        ->where('cards.active_equipment_id', $id)
                        ->groupBy(
                            'cards.id',
                            'cards.name',
                            'map_proyects.name'
                        );

            return datatables()->eloquent($query)->toJson();
        }

        public function destroyByObject($object): bool
        {
            if($object::class !== ActiveEquipment::class)
                return false;

            $cards = $object->cards;

            $cards->map(function($card){
                $ports = $card->ports;
                $transceivers = $card->transceivers;
                $equipmentLinkRespository = new EquipmentLinkRepository();

                if($ports->isNotEmpty()){
                    $ports->map(function($port) use($equipmentLinkRespository){
                        $equipmentLinkRespository->disconnectPort($port->id);
                        $this->delete($port);
                    });
                }

                if($transceivers->isNotEmpty()){
                    $transceiverRepository = new TransceiverRepository();

                    $transceivers->map(function($transceiver) use($equipmentLinkRespository, $transceiverRepository){
                        $ports = $transceiver->ports;
                        if($ports->isNotEmpty()){
                            $equipmentLinkRespository = new EquipmentLinkRepository();

                            $ports->map(function($port) use($equipmentLinkRespository){
                                $equipmentLinkRespository->disconnectPort($port->id);
                                $this->delete($port);
                            });
                        }

                        $transceiverRepository->delete($transceiver);

                    });
                }

                $this->delete($card);
            });


            return true;
        }
	}
?>
