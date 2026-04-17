<?php

namespace App\Http\Controllers\Module\Mapas;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Repositories\EquipmentLinkRepository;
use App\Repositories\MapLinkRepository;
use App\Repositories\PortRepository;
use App\Repositories\TableRepository;
use App\Services\SimpleService;
use Exception;
use Illuminate\Http\Request;

class EquipmentLinkController extends Controller
{
    protected $EquipmentLinkRepository;
    protected $MapLinkRepository;
    protected $PortRepository;
    protected $SimpleService;
    protected $TableRepository;

    public function __construct()
    {
        $this->EquipmentLinkRepository = new EquipmentLinkRepository();
        $this->MapLinkRepository = new MapLinkRepository();
        $this->PortRepository = new PortRepository();
        $this->SimpleService = new SimpleService();
        $this->TableRepository = new TableRepository();
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        return $this->EquipmentLinkRepository->getByMapRouteIdForDataTable($request->id);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        return $this->SimpleService->simpleTransaction(function() use($request){
                $this->EquipmentLinkRepository->save(
                    $request->input_id,
                    $request->output_id,
                );
        });
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function fusionUpdate(Request $request)
    {
        return $this->SimpleService->simpleTransaction(function() use($request) {


            $port = $this->PortRepository->find($request->port_id);

            if($request->connection_type === "splitter"){
                $firstPort = $this->PortRepository->find($request->splitter_port_id);
                $spliterInLinks = $firstPort->portable->input()->links();

                if($spliterInLinks->count() > 1)
                    throw new Exception("El puerto de entrada del splitter tiene mas de una conexión", 5525);

                $firstMapLink = $spliterInLinks->first()->mapLink;
            }else{
                $firstPort = $this->PortRepository->getByData(
                    $request->first_box_input_id,
                    $request->first_fiber_id
                );
                $firstMapLink = $this->MapLinkRepository->findByInputBox($request->first_box_input_id);
            }


            $secondPort = $this->PortRepository->getByData(
                $request->second_box_input_id,
                $request->second_fiber_id
            );

            if(!empty($secondPort->fusionPort()))
                    throw new Exception("el puerto selecionado o ruta selecionada no esta diponible para conexión", 5525);


            $secondMapLink = $this->MapLinkRepository->findByInputBox($request->second_box_input_id);

            $equipmentLinks = $port->links();

            if($equipmentLinks->isNotEmpty()){
                if($equipmentLinks->count()>2)
                    throw new Exception("El puerto de fusión tiene asiganado mas de una conexión", 5525);

                foreach($firstPort->links() AS $link){
                    $this->EquipmentLinkRepository->delete($link);
                }
            }

            $this->EquipmentLinkRepository->create([
                "input_id"=>$port->id,
                "output_id"=>$firstPort->id,
                "map_link_id"=>$firstMapLink->id,
                "fiber_id"=>$request->connection_type === "splitter"?$spliterInLinks->first()->fiber_id:$request->first_fiber_id
            ]);

            $this->EquipmentLinkRepository->create([
                "output_id"=>$port->id,
                "input_id"=>$secondPort->id,
                "map_link_id"=>$secondMapLink->id,
                "fiber_id"=>$request->second_fiber_id
            ]);

            return;
        });
    }

    public function fiberConnectionUpdate(Request $request)
    {
        return $this->SimpleService->simpleTransaction(function() use($request) {
            $inputPort = $this->PortRepository->find($request->input_id);

            $links = $inputPort->links();

            if($links->isNotEmpty()){
                $this->EquipmentLinkRepository->disconnectPassiveEquipmentPort($inputPort);
            }

            if($links->count()>1)
                throw new Exception("Este puerto tiene mas de una conexion pre-existente, favor de revisar", 5525);

            $this->EquipmentLinkRepository->connectPassiveEquipmentPort(
                $inputPort,
                $request->map_route_id,
                $request->fiber_id
            );
        });
    }

    public function splitterInConnectionUpdate(Request $request)
    {
        return $this->SimpleService->simpleTransaction(function() use($request) {
            $port = $this->PortRepository->find($request->port_id);

            $equipmentLinks = $port->links();

            $InputBoxPort = $this->PortRepository->getByData(
                $request->box_input_id,
                $request->fiber_id
            );

            $mapLink = $this->MapLinkRepository->findByInputBox($request->box_input_id);

            $equipmentLinkData = [
                "input_id"=>$port->id,
                "output_id"=>$InputBoxPort->id,
                "map_link_id"=>$mapLink->id,
                "fiber_id"=>$request->fiber_id
            ];

            if($equipmentLinks->isEmpty()){
                $this->EquipmentLinkRepository->create($equipmentLinkData);

                return;
            }

            if($equipmentLinks->count()>2)
                throw new Exception("El puerto de fusión tiene asiganado mas de una conexión", 5525);

            $this->EquipmentLinkRepository->update($equipmentLinks->first(),$equipmentLinkData);

            return;
        });
    }

    public function splitterOutConnectionUpdate(Request $request)
    {
        return $this->SimpleService->simpleTransaction(function() use($request) {
            if($request->connection_type === "fibra"){

                $port = $this->PortRepository->find($request->fusion_port_id);

                $firstPort = $this->PortRepository->find($request->port_id);

                $spliterInLinks = $firstPort->portable->input()->links();

                if($spliterInLinks->count() > 1)
                    throw new Exception("El puerto de entrada del splitter tiene mas de una conexión", 5525);

                $firstMapLink = $this->MapLinkRepository->findBySplitter($firstPort->portable_id);

                $secondPort = $this->PortRepository->getByData(
                    $request->box_input_id,
                    $request->fiber_id
                );

                if(!empty($secondPort->fusionPort()))
                    throw new Exception("el puerto selecionado o ruta selecionada no esta diponible para conexión", 5525);

                $secondMapLink = $this->MapLinkRepository->findByInputBox($request->box_input_id);

                $equipmentLinks = $port->links();

                if($equipmentLinks->isNotEmpty() || !empty($firstPort->fusionPort())){
                    if($equipmentLinks->count()>2)
                        throw new Exception("El puerto de fusión tiene asiganado mas de una conexión", 5525);

                    foreach($firstPort->fusionPort()->links() AS $link){
                        $this->EquipmentLinkRepository->delete($link);
                    }
                }

                foreach($firstPort->links() AS $link)
                {
                    $this->EquipmentLinkRepository->delete($link);
                }

                $this->EquipmentLinkRepository->create([
                    "input_id"=>$port->id,
                    "output_id"=>$firstPort->id,
                    "map_link_id"=>$firstMapLink->id,
                    "fiber_id"=>$spliterInLinks->first()->fiber_id
                ]);

                $this->EquipmentLinkRepository->create([
                    "output_id"=>$port->id,
                    "input_id"=>$secondPort->id,
                    "map_link_id"=>$secondMapLink->id,
                    "fiber_id"=>$request->fiber_id
                ]);

                return;
            }

            $inport = $this->PortRepository->find($request->port_id);
            $outport = $this->PortRepository->find($request->output_id);

            if(!empty($inport->fusionPort()))
            {
                foreach($inport->fusionPort()->links() AS $link){
                    $this->EquipmentLinkRepository->delete($link);
                }
            }

            $links = $inport->links();

            if($links->isEmpty()){
                $this->EquipmentLinkRepository->create([
                    "input_id"=>$inport->id,
                    "output_id"=>$outport->id,
                ]);

                return;
            }

            if($links->count() > 1)
                throw new Exception("El puerto tiene asiganado mas de una conexión", 5525);

            $this->EquipmentLinkRepository->update($links->first(),[
                (($links->first()->input_id === $inport->id)?"output_id":"input_id")=>$outport->id
            ]);

        });
    }
}
