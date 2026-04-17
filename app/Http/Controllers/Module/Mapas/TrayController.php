<?php

namespace App\Http\Controllers\Module\Mapas;

use App\Http\Controllers\Controller;
use App\Repositories\TrayRepository;
use App\Services\SimpleService;
use Illuminate\Http\Request;

class TrayController extends Controller
{
    protected $TrayRepository;
    protected $SimpleService;

    public function __construct()
    {
        $this->TrayRepository = new TrayRepository();
        $this->SimpleService = new SimpleService();
    }

    public function index(Request $request)
    {
        return $this->TrayRepository->getForDatatable($request->id);
    }

    public function list(Request $request)
    {
        return $this->TrayRepository->listByBox($request->object["id"], $request->text, $request->page);
    }
}
