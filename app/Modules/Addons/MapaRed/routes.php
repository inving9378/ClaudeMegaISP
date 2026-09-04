<?php

use App\Modules\Addons\MapaRed\Controllers\MapaRedController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'check_route_permission'])
    ->get('/mapa-red', [MapaRedController::class, 'index'])
    ->name('mapa-red.index');
