<?php

namespace App\Http\Controllers\Module\Mapas;

use App\Http\Controllers\Controller;
use App\Repositories\PassiveEquipmentRepository;
use App\Repositories\PortRepository;
use App\Services\SimpleService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\MapService;

class PassiveEquipmentController extends Controller
{
    protected $PassiveEquipmentRepository;
    protected $PortRepository;
    protected $MapService;
    protected $SimpleService;

    public function __construct(){
        $this->PassiveEquipmentRepository = new PassiveEquipmentRepository ();
        $this->PortRepository = new PortRepository ();
        $this->MapService = new MapService();
        $this->SimpleService = new SimpleService();
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        return $this->PassiveEquipmentRepository->getByRackIdForDataTable($request->id);
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
            $passiveEquipment = $this->PassiveEquipmentRepository->create($request->all());

            foreach(['entrada', 'fibra'] as $item){
                $this->PortRepository->createByData(
                    $passiveEquipment,
                    1,
                    [$item=>$passiveEquipment->type->ports]
                );
            }
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
    public function update(Request $request)
    {
        try{
            DB::beginTransaction();

            $equipment = $this->PassiveEquipmentRepository->find($request->id);

            $this->PassiveEquipmentRepository->update($equipment, $request->all());

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

            $passiveEquipment = $this->PassiveEquipmentRepository->find($request->id);
            $this->MapService->destroyObject($passiveEquipment);

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
