<?php

namespace App\Modules\Addons\Mapas\Controllers\Mapas;

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

    public function list(Request $request)
    {
        return $this->FiberRepository->SearchForSelectByBuffer($request->text, $request->page, $request->buffer_id, $request->map_route_id);
    }

    public function listByInputBox(Request $request)
    {
        return $this->FiberRepository->listByInputBox($request->text, $request->page, $request->buffer_id, $request->box_input_id);
    }
}
