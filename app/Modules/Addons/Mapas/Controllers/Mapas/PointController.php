<?php

namespace App\Modules\Addons\Mapas\Controllers\Mapas;

use App\Http\Controllers\Controller;
use App\Repositories\PointRepository;
use App\Services\MapService;
use App\Services\SimpleService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PointController extends Controller
{
    protected $MapService;
    protected $PointRepository;
    protected $SimpleService;

    public function __construct()
    {
        $this->MapService = new MapService();
        $this->PointRepository = new PointRepository();
        $this->SimpleService = new SimpleService();
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

            $point = $this->PointRepository->find($request->id);
            $this->MapService->destroyObject($point);

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
