<?php

namespace App\Repositories;

use App\Models\BoxInput;
use App\Models\EquipmentLink;
use App\Models\Port;
use App\Models\Site;
use App\Repositories\BaseRepository;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Francisco López Zetina
 */
	class EquipmentLinkRepository extends BaseRepository
	{
		public function getModel(): EquipmentLink
		{
			return new EquipmentLink();
		}

        public function findByData(int $id): ?EquipmentLink
        {
            return $this->getModel()
                        ->where([
                            ['input_id', $id],
                        ])
                        ->orWhere([
                            ['output_id', $id],
                        ])
                        ->first();
        }

        public function findByMultipleData(int $id1, int $id2): ?EquipmentLink
        {
            $equipmentLink = $this->findByData($id1);

            if(!empty($equipmentLink))
                return $equipmentLink;

            return $this->findByData($id2);
        }

        public function save(int $id1, int $id2): EquipmentLink
        {
            $equipmentLink = $this->findByData($id1);
            if(empty($equipmentLink))
                return $this->create([
                    "input_id" => $id1,
                    "output_id" => $id2,
                ]);

            if($equipmentLink->input_id === $id1)
                return $this->update($equipmentLink,[
                    "output_id" => $id2,
                ]);

            if($equipmentLink->output_id === $id1)
                return $this->update($equipmentLink,[
                    "input_id" => $id2,
                ]);
        }

        public function findByMapRouteAndFiber(int $mapRouteId, int $fiberId): Collection
        {
            return $this->getModel()
                        ->select(
                            "equipment_links.*"
                        )
                        ->join("map_links", "map_links.id", "equipment_links.map_link_id")
                        ->where('equipment_links.fiber_id', $fiberId)
                        ->where('map_links.map_route_id', $mapRouteId)
                        ->get();
        }

        public function getByMapRouteIdForDataTable(int $id)
        {
            $tableRepository = new TableRepository();
            $tables = $tableRepository->getHasConnection(true);

            $query = $this->getModel()
                        ->select(
                            "equipment_links.id",
                            "fibers.id AS fiber_number",
                            "fiber_colors.code AS fiber_color",
                            "buffers.id AS buffer_number",
                            "buffer_colors.code AS buffer_color",
                            DB::raw($this->getCaseForColumnSearch($tables, 'in')),
                            "in_ports.number AS inport_name",
                            DB::raw($this->getCaseForColumnSearch($tables, 'out')),
                            "out_ports.number AS outport_name",
                        )
                        ->join('fibers', 'fibers.id', 'equipment_links.fiber_id')
                        ->join('colors as fiber_colors', 'fiber_colors.id', 'fibers.color_id')
                        ->join('buffers', 'buffers.id', 'fibers.buffer_id')
                        ->join('colors as buffer_colors', 'buffer_colors.id', 'buffers.color_id')
                        ->leftJoin('ports AS in_ports', 'in_ports.id', 'equipment_links.input_id')
                        ->leftJoin('ports AS out_ports', 'out_ports.id', 'equipment_links.output_id')
                        ->where('equipment_links.map_link_id', $id);

            foreach($tables as $table){
                foreach (["in", "out"] as $type){
                    $query->leftJoin("$table->name AS ".$type."_$table->name", function($join) use($table, $type){
                        $join->on($type."_$table->name.id", $type."_ports.portable_id")
                            ->where($type."_ports.portable_type", $table->model_class);
                    });
                }
            }

            return datatables()->eloquent($query)->toJson();
        }

        public function getByPortId(int $portId):Collection
        {
            return $this->getModel()
                        ->where('input_id', $portId)
                        ->orWhere('output_id', $portId)
                        ->get();
        }

        public function disconnectPort(int $portId): bool
        {
            $links = $this->getByPortId($portId);

            if(empty($links))
                return false;

            foreach($links as $link){
                if($link->input_id === $portId){
                    $this->update($link,[
                        "input_id"=>null
                    ]);

                    continue;
                }elseif($link->output_id === $portId){
                    $this->update($link,[
                        "output_id"=>null
                    ]);

                    continue;
                }

                throw new Exception('Ningun elemento coincide con la busqueda de enlaces', 5525);
            }

            return true;
        }

        public function getPortsLinked(int $portId)
        {
            $equipmentLinks = $this->getByPortId($portId);

            return $equipmentLinks->map(function($equipmentLink) use($portId){
                if($equipmentLink->input_id === $portId){
                    return $equipmentLink->output_id;
                }elseif($equipmentLink->output_id === $portId){
                    return$equipmentLink->input_id;
                }
            });
        }

        public function getByMapRouteAndFiber(int $mapRouteId, int $fiberId, bool $inSite = false) : Collection
        {
            $query = $this->getModel()
                        ->select("equipment_links.*")
                        ->leftJoin("ports", function($join){
                            $join->on("equipment_links.input_id", "ports.id")
                            ->orWhereColumn("equipment_links.output_id", "ports.id");
                        })
                        ->leftJoin("map_links", "map_links.id", "equipment_links.map_link_id")
                        ->where('equipment_links.fiber_id', $fiberId)
                        ->where('map_links.map_route_id', $mapRouteId)
                        ->groupBy(
                            "equipment_links.id"
                        );

            if($inSite){
                $query->where('ports.portable_type', Site::class);
            }else{
                $query->whereNot('ports.portable_type', Site::class);
            }

            return $query->get();
        }

        public function disconnectPassiveEquipmentPort(Port $inputPort)
        {
            $equipmentLink = $inputPort->links()->first();

            $sitePort = (new PortRepository)->findSitePortUnconnected($inputPort->portable->rack->site_id);

            if($equipmentLink->input_id === $inputPort->id){
                return $this->update($equipmentLink,[
                    "input_id"=>$sitePort->id,
                ]);
            }

            return $this->update($equipmentLink,[
                "output_id"=>$sitePort->id,
            ]);
        }

        public function connectPassiveEquipmentPort(Port $inputPort, int $mapRouteId, int $fiberId)
        {
            $tablePortable = $inputPort->infoTable();

            if(!$tablePortable->in_site)
                throw new Exception("no es un puerto en sitio, por favor revisar", 5525);

            $equipmentLinks = $this->getByMapRouteAndFiber(
                $mapRouteId,
                $fiberId,
                $tablePortable->in_site
            );

            $outputPorts = (new PortRepository)->getByMapRouteAndFiber(
                $mapRouteId,
                $fiberId
            );

            $sitePort = $outputPorts->where("portable_type", Site::class)->first();

            if(empty($sitePort))
                throw new Exception("puerto no encontrado", 5525);

            if($equipmentLinks->count() > 1)
                throw new Exception("Este puerto tiene mas de una conexión, favor de revisar", 5525);

            $equipmentLink = $equipmentLinks->first();

            if($equipmentLink->input_id === $sitePort->id){
                $this->update($equipmentLink,[
                    "input_id"=>$inputPort->id,
                ]);

                return;
            }

            $this->update($equipmentLink,[
                "output_id"=>$inputPort->id,
            ]);

            return;
        }

        public function destroyByObject($object): bool
        {
            $ports = Port::where("portable_id", $object->id)->where("portable_type", $object::class)->get();

            foreach($ports as $port){
                foreach($port->links() as $link){
                    $this->delete($link);
                }
            }

            return true;
        }
	}
?>
