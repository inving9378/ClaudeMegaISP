<?php

namespace App\Modules\Core\Configuracion\Controllers;

use App\Http\Controllers\Controller;

class ModuleConfigPanelController extends Controller
{
    public function index()
    {
        return view('core-configuracion::config-panel-nueva');
    }
}
