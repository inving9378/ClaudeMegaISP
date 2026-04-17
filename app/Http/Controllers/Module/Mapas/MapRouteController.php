<?php

namespace App\Http\Controllers\Module\Mapas;

use App\Http\Controllers\Controller;
use App\Models\Color;
use App\Models\Port;
use App\Repositories\EquipmentLinkRepository;
use App\Repositories\MapLinkRepository;
use App\Repositories\MapRouteRepository;
use App\Repositories\PointRepository;
use App\Repositories\PortRepository;
use App\Repositories\PositionRepository;
use App\Repositories\TableRepository;
use App\Services\SimpleService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MapRouteController extends Controller
{
    protected $EquipmentLinkRepository;
    protected $PointRepository;
    protected $PositionRepository;
    protected $PortRepository;
    protected $MapLinkRepository;
    protected $MapRouteRepository;
    protected $SimpleService;
    protected $TableRepository;

    public function __construct()
    {
        $this->EquipmentLinkRepository = new EquipmentLinkRepository;
        $this->PointRepository = new PointRepository();
        $this->PositionRepository = new PositionRepository();
        $this->PortRepository = new PortRepository;
        $this->MapLinkRepository = new MapLinkRepository();
        $this->MapRouteRepository = new MapRouteRepository();
        $this->TableRepository = new TableRepository();
        $this->SimpleService = new SimpleService();
    }

    public function index(Request $request)
    {
        return $this->MapRouteRepository->listByFiberPort($request->object_id, $request->page);
    }
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $mapRoutes = $this->MapRouteRepository->getByPosition(
            $request->positions[0]["latitude"],
            $request->positions[0]["longitude"],
            $request->map_proyect_id
        );

        $colors = Color::all();

        return response()->json([
            'res' => true,
            'type' => 'map_route_create',
            'view' => view('meganet.module.mapas.objectForms.map_route', compact('mapRoutes', 'colors'))->render(),
        ], 200);
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
            $mapRoute = $this->MapRouteRepository->getOrCreate($request->all());

            $mapLinks = [];

            $count = 1;
            foreach($request->positions as $position){

                $object = $this->PointRepository->getOrCreateBy($request->map_proyect_id, $position["longitude"], $position["latitude"]);

                if($count === 1){
                    array_push($mapLinks, [
                        "input_id" => $object->id,
                        "input_type" => $object::class,
                    ]);
                }elseif($count === count($request->positions)){
                    $mapLinks[$count - 2]["output_id"] = $object->id;
                    $mapLinks[$count - 2]["output_type"] = $object::class;
                }else{
                    $mapLinks[$count - 2]["output_id"] = $object->id;
                    $mapLinks[$count - 2]["output_type"] = $object::class;
                    array_push($mapLinks, [
                        "input_id" => $object->id,
                        "input_type" => $object::class,
                    ]);
                }

                if(!empty($position["map_link_id"])){
                    $this->MapLinkRepository->insetObject(
                        $position["map_link_id"],
                        $object->id,
                        $object::class
                    );
                }

                $count++;
            }

            foreach($mapLinks as $item){
                $item["map_route_id"] = $mapRoute->id;
                $mapLink = $this->MapLinkRepository->create($item);

                for ($i=1; $i <= $mapRoute->fibers_amount ; $i++) {
                    $inputObject = $mapLink->inputObject;
                    $outputObject = $mapLink->outputObject;
                    if(!isset($lastMapLink)){
                        $inputPort = $this->PortRepository->findOrCreateAvailablePort(
                            number: $i,
                            type: Port::$continuo,
                            objectId: $inputObject->id,
                            objectType: $inputObject::class,
                            mapRouteId: $mapRoute->id
                        );
                    }else{
                        $inputPort = $lastMapLink->equipmentLinks->where("fiber_id", $i)->first()->outputObject;
                    }

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

                $lastMapLink = $mapLink;

            }
        });
    }

    /**
     * Display the specified resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {
        return response()->json([
            'res' => true,
            'type' => 'map_route',
            'view' => 'ssss',
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        try{
            DB::beginTransaction();

            $mapRoute = $this->MapRouteRepository->find($request->map_route_id);

            $this->MapRouteRepository->update($mapRoute, $request->all());

            DB::commit();

            return response()->json([
                'res' => true,
                'message' => 'Guardado',
            ], 200);
        }catch(Exception $e){
            return $this->SimpleService->catch($e);
        }
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
}
