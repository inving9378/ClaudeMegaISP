<?php

namespace App\Modules\Addons\Mapas\Controllers\Mapas;

use App\Http\Controllers\Controller;
use App\Repositories\BoxTypeRepository;
use App\Services\SimpleService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BoxTypeController extends Controller
{
    protected $BoxTypeRepository;
    protected $SimpleService;

    public function __construct()
    {
        $this->BoxTypeRepository = new BoxTypeRepository();
        $this->SimpleService = new SimpleService();
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return $this->BoxTypeRepository->getForDatatable();
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
            $this->BoxTypeRepository->create($request->all());
        });
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
            $boxType = $this->BoxTypeRepository->find($request->object_id);
            $this->BoxTypeRepository->update($boxType, $request->all());
        });
    }

}
