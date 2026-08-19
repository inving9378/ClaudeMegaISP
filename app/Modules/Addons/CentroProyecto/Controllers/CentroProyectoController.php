<?php

namespace App\Modules\Addons\CentroProyecto\Controllers;

use Illuminate\Routing\Controller;

class CentroProyectoController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->can('centro-proyecto.view'), 403);

        return view('addon-centro-proyecto::index');
    }
}
