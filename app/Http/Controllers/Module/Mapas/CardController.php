<?php

namespace App\Http\Controllers\Module\Mapas;

use App\Http\Controllers\Controller;
use App\Repositories\CardRepository;
use App\Repositories\PortRepository;
use App\Services\MapService;
use App\Services\SimpleService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CardController extends Controller
{
    protected $CardRepository;
    protected $PortRepository;
    protected $SimpleService;
    protected $MapService;

    public function __construct(){
        $this->CardRepository = new CardRepository ();
        $this->PortRepository = new PortRepository ();
        $this->SimpleService = new SimpleService ();
        $this->MapService = new MapService();
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        return $this->CardRepository->getByRackIdForDataTable($request->id);
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
                $parms = $request->all();
                $card = $this->CardRepository->create($parms);
                $data = [];

                if(array_key_exists("number_gibicC+_ports",$parms))
                    $data["gibic C+"] = $parms["number_gibicC+_ports"];

                if(array_key_exists("number_gibicC++_ports",$parms))
                    $data["gibic C++"] = $parms["number_gibicC++_ports"];

                if(array_key_exists("number_SFP_ports",$parms))
                    $data["SFP"] = $parms["number_SFP_ports"];

                if(array_key_exists("number_SFP+_ports",$parms))
                    $data["SFP+"] = $parms["number_SFP+_ports"];

                if(array_key_exists("number_ethernet_ports",$parms))
                    $data["ethernet"] = $parms["number_ethernet_ports"];

                $count = 0;
                foreach($data as $item){
                    $count += $item;
                }

                $this->PortRepository->createByData($card, 0, $data);

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

            $card = $this->CardRepository->find($request->card_id);

            $this->CardRepository->update($card, [
                "name" => $request->card_name,
                "map_proyect_id" => $request->map_proyect_id
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

            $card = $this->CardRepository->find($request->id);
            $this->MapService->destroyObject($card);

            DB::commit();

            return response()->json([
                'res' => true,
                'message' => 'Guardado',
            ], 200);
        }catch(Exception $e){
            $this->SimpleService->catch($e);
        }
    }

    public function list(Request $request)
    {
        $data = $this->CardRepository->SearchForSelect(
            $request->text,
            [$request->id],
            $request->page,
        );
        return response()->json($data, 200);
    }
}
