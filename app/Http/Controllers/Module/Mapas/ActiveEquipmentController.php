<?php

namespace App\Http\Controllers\Module\Mapas;

use App\Http\Controllers\Controller;
use App\Repositories\ActiveEquipmentRepository;
use App\Repositories\PortRepository;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\MapService;
use App\Services\SimpleService;

class ActiveEquipmentController extends Controller
{
    protected $ActiveEquipmentRepository;
    protected $MapService;
    protected $PortRepository;
    protected $SimpleService;

    public function __construct(){
        $this->ActiveEquipmentRepository = new ActiveEquipmentRepository ();
        $this->MapService = new MapService();
        $this->PortRepository = new PortRepository ();
        $this->SimpleService = new SimpleService();
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        return $this->ActiveEquipmentRepository->getByRackIdForDataTable($request->id);
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
        try{
            DB::beginTransaction();
            $ActiveEquipment = $this->ActiveEquipmentRepository->create($request->all());

            $type = $ActiveEquipment->type;

            $this->PortRepository->createByData(
                $ActiveEquipment,
                1,
                [
                    "ethernet"=>$type->ethernet_ports,
                    "SFP"=>$type->sfp_ports,
                    "SFP+"=>$type->sfp_plus_ports
                ]
            );

            DB::commit();

            return response()->json([
                'res' => true,
                'message' => 'Guardado',
            ], 200);
        }catch(Exception $e){
            $this->SimpleService->catch($e);
        }
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
    public function update(Request $request)
    {
        try{
            DB::beginTransaction();

            $equipment = $this->ActiveEquipmentRepository->find($request->active_equipment_id);

            $this->ActiveEquipmentRepository->update($equipment, [
                "name" => $request->active_equipment_name,
                "type_id" => $request->type_id,
                "description" => $request->description,
                "serial_number" => $request->serial_number,
                "rack_id" => $request->active_equipment_rack_id
            ]);

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

            $activeEquipment = $this->ActiveEquipmentRepository->find($request->id);
            $this->MapService->destroyObject($activeEquipment);

            DB::commit();

            return response()->json([
                'res' => true,
                'message' => 'Guardado',
            ], 200);
        }catch(Exception $e){
            $this->SimpleService->catch($e);
        }
    }
}
