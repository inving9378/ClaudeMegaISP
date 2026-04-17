<?php

namespace App\Http\Controllers\Module\Mapas;

use App\Http\Controllers\Controller;
use App\Repositories\PoleRepository;
use App\Repositories\PositionRepository;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use MatanYadaev\EloquentSpatial\Objects\Point;
use App\Services\MapService;
use App\Services\SimpleService;

class PoleController extends Controller
{
    protected $PoleRepository;
    protected $PositionRepository;
    protected $MapService;
    protected $SimpleService;

    public function __construct(){
        $this->PoleRepository = new PoleRepository ();
        $this->PositionRepository = new PositionRepository ();
        $this->MapService = new MapService();
        $this->SimpleService = new SimpleService();
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
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
        //
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

            //dd($request->all());

            $pole = $this->PoleRepository->find($request->pole_id);

            $this->PoleRepository->update($pole, [
                "description" => $request->pole_description,
                "height" => $request->height,
                "type" => $request->type,
                "tension" => $request->tension
            ]);

            $this->PositionRepository->update($pole->position,[
                "point" => new Point($request->latitude, $request->longitude)
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

            $pole = $this->PoleRepository->find($request->id);
            $this->MapService->destroyObject($pole);

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
