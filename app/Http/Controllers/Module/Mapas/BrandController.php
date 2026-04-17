<?php

namespace App\Http\Controllers\Module\Mapas;

use App\Http\Controllers\Controller;
use App\Repositories\BrandRepository;
use App\Services\SimpleService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BrandController extends Controller
{
    protected $BrandRepository;
    protected $SimpleService;

    public function __construct()
    {
        $this->BrandRepository = new BrandRepository();
        $this->SimpleService = new SimpleService();
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return $this->BrandRepository->getForDatatable();
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
                $this->BrandRepository->create($request->all());
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
            $brand = $this->BrandRepository->find($request->object_id);
            $this->BrandRepository->update($brand, ["name" => $request->name]);
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

    /**
     * Get list to select.
     *
     * @param   $pag
     *  * @param  string  $text
     * @return \Illuminate\Http\Response
     */
    public function getListToSelect(Request $request)
    {
        $brands = $this->BrandRepository->getListToSelect($request->text, $request->page);
        return response()->json($brands, 200);
    }
}
