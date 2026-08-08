<?php

namespace App\Modules\Addons\Mapas\Controllers\Mapas;

use App\Http\Controllers\Controller;
use App\Repositories\TubeRepository;
use App\Repositories\TubeTypeRepository;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TubeController extends Controller
{
    protected $TubeRepository;
    protected $TubeTypeRepository;

    public function __construct()
    {
        $this->TubeRepository = new TubeRepository();
        $this->TubeTypeRepository = new TubeTypeRepository();
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return $this->TubeRepository->getForDatatable();
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
            $tubeType = $this->TubeTypeRepository->create([
                'type' => $request->type,
                'diameter' => $request->diameter
            ]);
            $tube = $this->TubeRepository->create([
                'tube_type_id' => $tubeType->id
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

}
