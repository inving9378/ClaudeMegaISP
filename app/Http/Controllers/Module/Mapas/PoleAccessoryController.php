<?php

namespace App\Http\Controllers\Module\Mapas;

use App\Http\Controllers\Controller;
use App\Models\PoleAccessory;
use App\Repositories\PoleAccessoryRepository;
use App\Services\SimpleService;
use Illuminate\Http\Request;

class PoleAccessoryController extends Controller
{
    protected $PoleAccessoryRepository;
    protected $SimpleService;

    public function __construct()
    {
        $this->PoleAccessoryRepository = new PoleAccessoryRepository();
        $this->SimpleService = new SimpleService();
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        return $this->PoleAccessoryRepository->getByPoleIdForDatatable($request->id);
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
            $this->PoleAccessoryRepository->create($request->all());
        });
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\PoleAccessory  $poleAccessory
     * @return \Illuminate\Http\Response
     */
    public function show(PoleAccessory $poleAccessory)
    {

    }


    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\PoleAccessory  $poleAccessory
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        return $this->SimpleService->simpleTransaction(function() use($request){
            $object = $this->PoleAccessoryRepository->find($request->id);
            $this->PoleAccessoryRepository->update($object, $request->all());
        });
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\PoleAccessory  $poleAccessory
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {
        return $this->SimpleService->simpleTransaction(function() use($request){
            $object = $this->PoleAccessoryRepository->find($request->id);
            $this->PoleAccessoryRepository->delete($object);
        });
    }
}
