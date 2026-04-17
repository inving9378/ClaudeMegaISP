<?php

namespace App\Http\Controllers\Module\Mapas;

use App\Http\Controllers\Controller;
use App\Repositories\BoxRepository;
use App\Repositories\EquipmentLinkRepository;
use App\Repositories\MapProyectRepository;
use App\Repositories\PointRepository;
use App\Repositories\PoleRepository;
use App\Repositories\TrenchRepository;
use App\Services\MapService;
use App\Services\SimpleService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MapProyectController extends Controller
{
    protected $BoxRepository;
    protected $EquipmentLinkRepository;
    protected $MapProyectRepository;
    protected $MapService;
    protected $PoleRepository;
    protected $PointRepository;
    protected $SimpleService;
    protected $TrenchRepository;

    public function __construct()
    {
        $this->BoxRepository = new BoxRepository ();
        $this->EquipmentLinkRepository = new EquipmentLinkRepository ();
        $this->MapProyectRepository = new MapProyectRepository ();
        $this->MapService = new MapService ();
        $this->PoleRepository = new PoleRepository ();
        $this->PointRepository = new PointRepository ();
        $this->SimpleService = new SimpleService ();
        $this->TrenchRepository = new TrenchRepository ();
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
        try{
            DB::beginTransaction();


            $this->MapProyectRepository->create(['name' => $request->proyect_name]);

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
    public function destroy(Request $request)
    {
        return $this->SimpleService->simpleTransaction(function() use($request){
            $mapProyect = $this->MapProyectRepository->find($request->proyect_id);
            foreach($mapProyect->sites AS $site){
                $this->MapService->destroyObject($site);
            }

            $mapProyect->poles->map(function($pole){
                $this->PoleRepository->delete($pole);
            });

            $mapProyect->points->map(function($point){
                $this->PointRepository->delete($point);
            });

            $mapProyect->trenches->map(function($trench){
                $this->PointRepository->delete($trench);
            });

            $mapProyect->boxes->map(function($box){
                $this->MapService->destroyObject($box);
            });

            $mapProyect->mapRoutes->map(function($mapRoute){
                $this->MapService->destroyObject($mapRoute);
            });

            $this->MapProyectRepository->delete($mapProyect);
        });
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
        $contacts = $this->MapProyectRepository->getListToSelect($request->text, $request->page);
        return response()->json($contacts, 200);
    }

}
