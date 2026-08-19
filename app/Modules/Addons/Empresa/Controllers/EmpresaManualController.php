<?php

namespace App\Modules\Addons\Empresa\Controllers;

use App\Http\Controllers\Controller;

class EmpresaManualController extends Controller
{
    /**
     * Manual General de la Empresa: documento vivo, autocontenido (no extiende
     * core-layout::master) para que su CSS/JS propios no interfieran con el
     * chrome del admin. Contenido de muestra hasta que Dirección cargue la
     * redacción oficial.
     */
    public function view()
    {
        return view('addon-empresa::manual');
    }
}
