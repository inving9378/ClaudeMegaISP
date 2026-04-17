<?php

namespace App\Repositories;

use App\Models\BoxInput;
use App\Models\MapRoute;
use App\Models\PassiveEquipment;
use App\Models\Port;
use App\Models\Site;
use App\Repositories\BaseRepository;
use App\Services\MapService;
use Illuminate\Support\Collection as SuportCollection;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Francisco López Zetina
 */
	class MapRouteRepository extends BaseRepository
	{
		public function getModel(): MapRoute
		{
			return new MapRoute();
		}

        public function listByFiberPort(int $portId, int $page)
        {
            return DB::table("map_routes")
                    ->select(
                        "map_routes.id",
                        "map_routes.name AS text"
                    )
                    ->join("map_links", "map_links.map_route_id", "map_routes.id")
                    ->join("racks", function($join){
                        $join->where(function($where){
                            $where->whereColumn("racks.site_id", "map_links.input_id")
                                ->where("map_links.input_type", Site::class);
                        })
                        ->orWhere(function($where){
                            $where->whereColumn("racks.site_id", "map_links.output_id")
                                ->where("map_links.output_type", Site::class);
                        });
                    })
                    ->join("passive_equipments", "passive_equipments.rack_id", "racks.id")
                    ->join("ports", function($join){
                        $join->on("ports.portable_id", "passive_equipments.id")
                            ->where("ports.portable_type", PassiveEquipment::class);
                    })
                    ->where("ports.id", $portId)
                    ->where("ports.type", Port::$fibra)
                    ->paginate(7, $page);
        }

        public function getOrCreate(array $parms): MapRoute
        {
            if(array_key_exists('map_route_id', $parms))
                return $this->find($parms["map_route_id"]);

            return $this->create($parms);
        }

        public function getByPosition(float $latitude, float $longitude, int $mapProyectId): SuportCollection
        {
            $positionRepository = new PositionRepository();

            $object = $positionRepository->getObject(
                $longitude,
                $latitude,
                $mapProyectId
            );

            if(empty($object))
                return collect();

            return $this->getByObject($object);
        }

        public function getByObject($object) : SuportCollection
        {
            $mapLinkRepository = new MapLinkRepository();

            $mapLinks = $mapLinkRepository->getMapLinksByObject($object->id, $object::class);

            $mapRoutes = $mapLinks->map(function($mapLink){
                $mapRoute = $mapLink->mapRoute;
                if(!empty($mapRoute))
                    return $mapRoute;
            });

            $duplicateMapRouteIds = $mapRoutes->duplicates()->map(function($mapRoute){
                return $mapRoute->id;
            });

            if($duplicateMapRouteIds->isEmpty())
                return $mapRoutes;

            return $mapRoutes->whereNotIn('id', $duplicateMapRouteIds);
        }

        public function destroyByObject($object): bool
        {
            if($object::class !== MapRoute::class)
                return false;

            $mapLinkRepository = new MapLinkRepository ();

            $object->mapLinks->map(function($mapLink) use($mapLinkRepository){
                $equipmentLinkRepository = new EquipmentLinkRepository ();
                $mapLink->equipmentLinks->map(function($equipmentLink) use($equipmentLinkRepository){
                    $equipmentLinkRepository->delete($equipmentLink);
                });
                $mapLinkRepository->delete($mapLink);
            });

            $this->delete($object);

            return true;
        }
	}
?>
