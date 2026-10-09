<?php

namespace App\Modules\Addons\MapaRed\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class MapaRedController extends Controller
{
    public function index(): View
    {
        return view('addon-mapa-red::index');
    }

    // MR-08 Fase 3 (item roadmap #9990534) — página dedicada de administración de
    // catálogos (cables/conectores/cajas/splitters), separada del mapa.
    public function catalogos(): View
    {
        return view('addon-mapa-red::catalogos');
    }
}
