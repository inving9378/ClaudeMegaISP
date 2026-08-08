<?php

namespace App\Modules\Addons\Mapas\Controllers\Mapas;

use App\Http\Controllers\Controller;
use App\Repositories\BufferRepository;
use Illuminate\Http\Request;

class BufferController extends Controller
{
    protected $BufferRepository;

    public function __construct()
    {
        $this->BufferRepository = new BufferRepository();
    }

    public function list(Request $request)
    {
        return $this->BufferRepository->SearchForSelectByMapRoute($request->text, $request->page, $request->object["id"]);
    }

    public function listByInputBox(Request $request)
    {
        return $this->BufferRepository->listByInputBox($request->text, $request->page, $request->object["id"]);
    }
}
