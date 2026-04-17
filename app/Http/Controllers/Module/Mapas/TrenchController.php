<?php

namespace App\Http\Controllers\Module\Mapas;

use App\Http\Controllers\Controller;
use App\Repositories\TrenchRepository;
use App\Services\MapService;
use App\Services\SimpleService;
use Illuminate\Http\Request;

class TrenchController extends Controller
{
    protected $MapService;
    protected $TrenchRepository;
    protected $SimpleService;

    public function __construct()
    {
        $this->MapService = new MapService();
        $this->TrenchRepository = new TrenchRepository();
        $this->SimpleService = new SimpleService();
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return $this->TrenchRepository->getForDatatable();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        return $this->SimpleService->simpleTransaction(function () use ($request){
            $this->TrenchRepository->create($request->all());
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
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        return $this->SimpleService->simpleTransaction(function () use ($request){
            $object = $this->TrenchRepository->find($request->id);
            $this->TrenchRepository->update($object, $request->all());
        });
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {
        return $this->SimpleService->simpleTransaction(function () use ($request){
            $object = $this->TrenchRepository->find($request->id);
            $this->MapService->destroyObject($object);
        });
    }
}
