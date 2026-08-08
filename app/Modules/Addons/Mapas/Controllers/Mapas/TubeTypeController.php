<?php

namespace App\Modules\Addons\Mapas\Controllers\Mapas;

use App\Http\Controllers\Controller;
use App\Repositories\TubeTypeRepository;
use App\Services\SimpleService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TubeTypeController extends Controller
{
    protected $SimpleService;
    protected $TubeTypeRepository;

    public function __construct()
    {
        $this->SimpleService = new SimpleService();
        $this->TubeTypeRepository = new TubeTypeRepository();
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return $this->TubeTypeRepository->getForDatatable();
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
            $this->TubeTypeRepository->create([
                'type' => $request->type,
                'diameter' => $request->diameter
            ]);
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
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        return $this->SimpleService->simpleTransaction(function() use($request){
            $tubeType = $this->TubeTypeRepository->find($request->object_id);
            $this->TubeTypeRepository->update($tubeType, $request->all());
        });
    }

}
