<?php

namespace App\Http\Controllers\Module\Mapas;

use App\Http\Controllers\Controller;
use App\Models\Port;
use App\Repositories\PortRepository;
use App\Repositories\SplitterRepository;
use App\Services\MapService;
use App\Services\SimpleService;
use Illuminate\Http\Request;

class SplitterController extends Controller
{
    protected $PortRepository;
    protected $SplitterRepository;
    protected $SimpleService;
    protected $MapService;

    public function __construct()
    {
        $this->PortRepository = new PortRepository();
        $this->SplitterRepository = new SplitterRepository();
        $this->SimpleService = new SimpleService();
        $this->MapService = new MapService();
    }

    public function index(Request $request)
    {
        return $this->SplitterRepository->getForDatatable($request->id);
    }

    public function store(Request $request)
    {
        return $this->SimpleService->simpleTransaction(function() use($request){
            $object = $this->SplitterRepository->create($request->all());

            $this->PortRepository->createByData(
                $object,
                0,
                [
                    "splitter_in" => 1,
                    "splitter_out" => $object->outputs
                ]
            );
        });
    }

    public function destroy(Request $request)
    {
        return $this->SimpleService->simpleTransaction(function() use($request){
            $object = $this->SplitterRepository->find($request->id);
            $this->MapService->destroyObject($object);
        });
    }

    public function list(Request $request)
    {
        return $this->SplitterRepository->ListByFusionPort($request->object["id"], $request->text, $request->page);
    }
}
