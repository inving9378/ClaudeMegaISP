<?php

namespace App\Modules\Addons\Mapas\Controllers\Mapas;

use App\Http\Controllers\Controller;
use App\Repositories\ActiveEquipmentTypeRepository;
use App\Services\SimpleService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActiveEquipmentTypeController extends Controller
{
    protected $ActiveEquipmentTypeRepository;
    protected $SimpleService;

    public function __construct(){
        $this->ActiveEquipmentTypeRepository = new ActiveEquipmentTypeRepository ();
        $this->SimpleService = new SimpleService ();
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return $this->ActiveEquipmentTypeRepository->getForDatatable();
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

            $this->ActiveEquipmentTypeRepository->create($request->all());

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
    public function update(Request $request)
    {
        return $this->SimpleService->simpleTransaction(function() use($request){
            $activeEquipmentType = $this->ActiveEquipmentTypeRepository->find($request->object_id);
            $this->ActiveEquipmentTypeRepository->update($activeEquipmentType, $request->all());
        });
    }

}
