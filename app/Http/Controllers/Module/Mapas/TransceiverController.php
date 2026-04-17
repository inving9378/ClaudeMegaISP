<?php

namespace App\Http\Controllers\Module\Mapas;

use App\Http\Controllers\Controller;
use App\Models\Port;
use App\Models\Transceiver;
use App\Repositories\EquipmentLinkRepository;
use App\Repositories\PortRepository;
use App\Repositories\TransceiverRepository;
use App\Services\SimpleService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransceiverController extends Controller
{
    protected $EquipmentLinkRepository;
    protected $PortRepository;
    protected $TransceiverRepository;
    protected $SimpleService;

    public function __construct()
    {
        $this->EquipmentLinkRepository = new EquipmentLinkRepository();
        $this->PortRepository = new PortRepository();
        $this->TransceiverRepository = new TransceiverRepository();
        $this->SimpleService = new SimpleService();
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        return $this->TransceiverRepository->getForDatatable($request->id);
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

            $transceiver = $this->TransceiverRepository->create($request->all());

            $port = $this->PortRepository->create([
                'number'=>0,
                'type'=>Port::$entrada,
                'portable_id'=>$transceiver->id,
                'portable_type'=>$transceiver::class,
            ]);

            $this->EquipmentLinkRepository->create([
                "input_id" => $port->id,
                "input_type" => Port::class,
                "output_id" => $request->port_id,
                "output_type" => Port::class
            ]);

            $ports = [1];

            if($request->type === Transceiver::$lsDual)
                array_push($ports, 2);

            foreach($ports as $number){
                $this->PortRepository->create([
                    'number'=>$number,
                    'type'=>Port::$jumper,
                    'portable_id'=>$transceiver->id,
                    'portable_type'=>$transceiver::class,
                ]);
            }

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
}
