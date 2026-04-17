<?php

namespace App\Http\Controllers\Module\Mapas;

use App\Http\Controllers\Controller;
use App\Models\Color;
use App\Models\Port;
use App\Repositories\EquipmentLinkRepository;
use App\Repositories\MapLinkRepository;
use App\Repositories\MapProyectRepository;
use App\Repositories\MapRouteRepository;
use App\Repositories\PortRepository;
use App\Repositories\TableRepository;
use App\Services\SimpleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class MaplinkController extends Controller
{
    protected $EquipmentLinkRepository;
    protected $MapLinkRepository;
    protected $MapRouteRepository;
    protected $MapProyectRepository;
    protected $PortRepository;
    protected $SimpleService;
    protected $TableRepository;

    public function __construct()
    {
        $this->EquipmentLinkRepository = new EquipmentLinkRepository();
        $this->MapLinkRepository = new MapLinkRepository();
        $this->MapRouteRepository = new MapRouteRepository();
        $this->MapProyectRepository = new MapProyectRepository();
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
        $table = $this->TableRepository->findByType($request->type);

        return $this->MapLinkRepository->getMapLinksByObjectForDataTable($request->id, $table->model_class);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        return $this->SimpleService->simpleTransaction(function() use ($request) {
            $mapRoute = $this->MapRouteRepository->getOrCreate($request->all());

            $inputTable = $this->TableRepository->findByType($request->input_type);
            $outputTable = $this->TableRepository->findByType(
                $request->output_type === "box"?"box_input":$request->output_type
            );

            $inputRespository = App::make($inputTable->repository_class);
            $outputRespository = App::make($outputTable->repository_class);

            $inputObject = $inputRespository->find($request->input_id);
            $outputObject = $outputRespository->find(
                $request->output_type === "box"?$request->box_input_id:$request->output_id
            );

            $mapLink = $this->MapLinkRepository->create([
                "map_route_id" => $mapRoute->id,
                "input_id"=>$inputObject->id,
                "input_type"=>$inputTable->model_class,
                "output_id"=>$outputObject->id,
                "output_type"=>$outputTable->model_class,
            ]);

            $fiberAmount = isset($request->map_route_id)?$mapRoute->fibers_amount:$request->fibers_amount;

            for ($i=1; $i <= $fiberAmount ; $i++) {

                $inputPort = $this->PortRepository->findOrCreateAvailablePort(
                    number: $i,
                    type: Port::$continuo,
                    objectId: $inputObject->id,
                    objectType: $inputObject::class,
                    mapRouteId: $mapRoute->id
                );

                $outputPort = $this->PortRepository->findOrCreateAvailablePort(
                    number: $i,
                    type: Port::$continuo,
                    objectId: $outputObject->id,
                    objectType: $outputObject::class,
                    mapRouteId: $mapRoute->id
                );

                $this->EquipmentLinkRepository->create([
                    "input_id"=>$inputPort->id,
                    "output_id"=>$outputPort->id,
                    'map_link_id'=>$mapLink->id,
                    'fiber_id'=>$i,
                ]);
            }
        });
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
        $mapLink = $this->MapLinkRepository->find($request->id);

        $mapProyects = $this->MapProyectRepository->getAll();

        $colors = Color::all();

        return response()->json([
            'res' => true,
            'type' => "map_link",
            'view' => view('meganet.module.mapas.data.map_link', compact("mapLink", "mapProyects", "colors"))->render()
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {
        return $this->SimpleService->simpleTransaction(function() use($request){
            $mapLink = $this->MapLinkRepository->find($request->id);

            foreach($mapLink->equipmentLinks as $equipmentLink){
                $this->EquipmentLinkRepository->delete($equipmentLink);
            }

            $this->MapLinkRepository->delete($mapLink);
        });
    }
}
