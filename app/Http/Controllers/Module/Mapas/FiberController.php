<?php

namespace App\Http\Controllers\Module\Mapas;

use App\Http\Controllers\Controller;
use App\Repositories\FiberRepository;
use Illuminate\Http\Request;

class FiberController extends Controller
{
    protected $FiberRepository;

    public function __construct()
    {
        $this->FiberRepository = new FiberRepository();
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
    public function update(Request $request, $id)
    {
        //
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

    public function list(Request $request)
    {
        return $this->FiberRepository->SearchForSelectByBuffer($request->text, $request->page, $request->buffer_id, $request->map_route_id);
    }

    public function listByInputBox(Request $request)
    {
        return $this->FiberRepository->listByInputBox($request->text, $request->page, $request->buffer_id, $request->box_input_id);
    }
}
