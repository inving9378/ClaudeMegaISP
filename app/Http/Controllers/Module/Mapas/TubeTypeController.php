<?php

namespace App\Http\Controllers\Module\Mapas;

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
        return $this->SimpleService->simpleTransaction(function() use($request){
            $tubeType = $this->TubeTypeRepository->find($request->object_id);
            $this->TubeTypeRepository->update($tubeType, $request->all());
        });
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
