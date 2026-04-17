<?php

namespace App\Http\Controllers\Module\Mapas;

use App\Http\Controllers\Controller;
use App\Repositories\PositionRepository;
use App\Repositories\SiteRepository;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use MatanYadaev\EloquentSpatial\Objects\Point;
use App\Services\MapService;
use App\Services\SimpleService;

class SiteController extends Controller
{
    protected $PositionRepository;
    protected $SiteRepository;
    protected $MapService;
    protected $SimpleService;

    public function __construct()
    {
        $this->PositionRepository = new PositionRepository();
        $this->SiteRepository = new SiteRepository();
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

            $site = $this->SiteRepository->find($request->site_id);

            $this->SiteRepository->update($site, ["name" => $request->modal_name]);

            $this->PositionRepository->update($site->position,[
                "point" => new Point($request->modal_latitude, $request->modal_longitude)
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

            $site = $this->SiteRepository->find($request->id);
            $this->MapService->destroyObject($site);

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
     * Get list to select.
     *
     * @param   $pag
     *  * @param  string  $text
     * @return \Illuminate\Http\Response
     */
    public function getListToSelect(Request $request)
    {
        $site = $this->SiteRepository->getListToSelect($request->text, $request->page);
        return response()->json($site, 200);
    }
}
