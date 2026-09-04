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
}
