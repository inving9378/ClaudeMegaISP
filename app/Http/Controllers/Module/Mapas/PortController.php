<?php

namespace App\Http\Controllers\Module\Mapas;

use App\Http\Controllers\Controller;
use App\Models\PassiveEquipment;
use App\Models\Port;
use App\Models\Splitter;
use App\Models\Table;
use App\Models\Tray;
use App\Repositories\EquipmentLinkRepository;
use App\Repositories\MapProyectRepository;
use App\Repositories\PortRepository;
use App\Repositories\TableRepository;
use App\Services\MapService;
use App\Services\SimpleService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

class PortController extends Controller
{
    protected $EquipmentLinkRepository;
    protected $MapProyectRepository;
    protected $MapService;
    protected $PortRepository;
    protected $TableRepository;
    protected $SimpleService;

    public function __construct()
    {
        $this->EquipmentLinkRepository = new EquipmentLinkRepository();
        $this->MapProyectRepository = new MapProyectRepository();
        $this->MapService = new MapService();
        $this->PortRepository = new PortRepository();
        $this->TableRepository = new TableRepository();
        $this->SimpleService = new SimpleService();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $table = $this->TableRepository->findByType($request->type);

        if($table->model_class === Tray::class)
            return $this->PortRepository->getByFiberForDataTable($request->id, $table->model_class);

        if($table->model_class === Splitter::class)
            return $this->PortRepository->getForDataTableSpliter($request->id, $table->model_class, $request->extra);

        return $this->PortRepository->getByDataForDataTable($request->id, $table->model_class, $request->extra);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        try{
            DB::beginTransaction();
                (array) $data = $request->all();
                $table = $this->TableRepository->findByType($data["portable_type"]);
                $data["portable_type"] = $table->model_class;
                $this->PortRepository->create($data);
            DB::commit();
            return response()->json([
                'res' => true,
                'message' => 'Guardado',
            ], 200);
        }catch(Exception $e){
            DB::rollBack();
            dd($e);
            return response()->json([
                'res' => false,
                'message' => 'Ha ocurrido un error',
            ], 490);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request)
    {

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

            $port = $this->PortRepository->find($request->port_id);

            $this->PortRepository->update($port, ["name" => $request->port_number]);

            DB::commit();

            return response()->json([
                'res' => true,
                'message' => 'Guardado',
            ], 200);
        }catch(Exception $e){
            DB::rollBack();
            return response()->json([
                'res' => false,
                'message' => 'Ha ocurrido un error',
            ], 490);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {
        try{
            DB::beginTransaction();

            $port = $this->PortRepository->find($request->id);
            $this->MapService->destroyObject($port);

            DB::commit();

            return response()->json([
                'res' => true,
                'message' => 'Guardado',
            ], 200);
        }catch(Exception $e){
            $this->SimpleService->catch($e);
        }
    }


    public function search(Request $request)
    {
        $object = $request->object;

        $data = $this->PortRepository->SearchForSelect(
            $request->text,
            [$request->id],
            $request->page,
            function ($query) use($object){
                $repository = new TableRepository();
                $table = $repository->findByType($object["type"]);

                $query->leftJoin('equipment_links', function($join){
                        $join
                        ->where(function($query){
                            $query->whereColumn('equipment_links.input_id', 'ports.id')
                                ->where('equipment_links.input_type', Port::class);
                        })
                        ->orWhere(function($query){
                            $query->whereColumn('equipment_links.output_id', 'ports.id')
                                ->where('equipment_links.output_type', Port::class);
                        });
                    })
                    ->where('portable_type', '=', $table->model_class)
                    ->where('portable_id', '=', $object["id"])
                    ->whereNull('equipment_links.id');
            }
        );

        return response()->json($data, 200);
    }

    public function passiveEquipmentIndex(Request $request)
    {
        return $this->PortRepository->getForSpecialTable($request->id);
    }

    public function passiveEquipmentShow(Request $request)
    {
        $table = $this->TableRepository->findByString(Port::class);

        $object = $this->PortRepository->findByObjectAndName(
            $request->passive_equipment_id,
            PassiveEquipment::class,
            $request->port_number,
        );

        return response()->json([
            'res' => true,
            'type' => $table->type,
            'view' => $this->MapService->getDataForm($object, $table)
        ], 200);
    }

    public function listByObject(Request $request)
    {
        $table = $this->TableRepository->findByType($request->object["type"]);

        $ports = $this->PortRepository->getByObject($request->object["id"],$table->model_class);

        $withOutIds = [];

        foreach($ports as $port){
            $objectLinkeds = $this->EquipmentLinkRepository->getPortsLinked($port->id, Port::class);

            if($objectLinkeds->isNotEmpty())
                array_push($withOutIds, $port->id);
        }

        $data = $this->PortRepository->SearchForSelectByObject($request->text, $request->page, $request->object["id"], $table->model_class, $withOutIds);

        return response()->json($data, 200);
    }
}
