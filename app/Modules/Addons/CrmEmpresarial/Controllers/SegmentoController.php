<?php

namespace App\Modules\Addons\CrmEmpresarial\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\CrmEmpresarial\Models\CrmSegmento;

class SegmentoController extends Controller
{
    public function index()
    {
        return response()->json(CrmSegmento::where('activo', true)->orderBy('orden')->get());
    }
}
