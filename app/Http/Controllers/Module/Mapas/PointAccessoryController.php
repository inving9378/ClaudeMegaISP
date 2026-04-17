<?php

namespace App\Http\Controllers\Module\Mapas;

use App\Http\Controllers\Controller;
use App\Repositories\PointAccessoryRepository;
use App\Services\SimpleService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PointAccessoryController extends Controller
{
    protected $PointAccessoryRepository;
    protected $SimpleService;

    public function __construct()
    {
        $this->PointAccessoryRepository = new PointAccessoryRepository();
        $this->SimpleService = new SimpleService();
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        return $this->PointAccessoryRepository->getByPointIdForDatatable($request->id);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        return $this->SimpleService->simpleTransaction(function () use($request){
            $data = $request->all();
            $this->PointAccessoryRepository->create($data);
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
        return $this->SimpleService->simpleTransaction(function () use($request){
            $data = $request->all();

            $data["lenght"] = $data["final_meter"] - $data["initial_meter"];

            if($data["lenght"] < 0)
                throw new Exception("el metraje inicial no puede ser mayor al metraje final", 5525);

            $object = $this->PointAccessoryRepository->find($request->id);

            $this->PointAccessoryRepository->update($object, $data);
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
        return $this->SimpleService->simpleTransaction(function () use($request){
            $object = $this->PointAccessoryRepository->find($request->id);

            $this->PointAccessoryRepository->delete($object);
        });
    }
}
