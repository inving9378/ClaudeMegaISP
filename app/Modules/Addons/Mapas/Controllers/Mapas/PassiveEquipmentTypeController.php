<?php

namespace App\Modules\Addons\Mapas\Controllers\Mapas;

use App\Http\Controllers\Controller;
use App\Repositories\PassiveEquipmentTypeRepository;
use App\Services\SimpleService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PassiveEquipmentTypeController extends Controller
{
    protected $PassiveEquipmentTypeRepository;
    protected $SimpleService;

    public function __construct()
    {
        $this->PassiveEquipmentTypeRepository = new PassiveEquipmentTypeRepository();
        $this->SimpleService = new SimpleService();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return $this->PassiveEquipmentTypeRepository->getForDatatable();
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

            $this->PassiveEquipmentTypeRepository->create($request->all());

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
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        return $this->SimpleService->simpleTransaction(function() use($request){
            $passiveEquipmentType = $this->PassiveEquipmentTypeRepository->find($request->object_id);
            $this->PassiveEquipmentTypeRepository->update($passiveEquipmentType, $request->all());
        });
    }

}
