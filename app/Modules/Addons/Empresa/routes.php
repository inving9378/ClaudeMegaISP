<?php

use App\Modules\Addons\Empresa\Controllers\EmpresaManualController;
use Illuminate\Support\Facades\Route;

/*
 * Rutas del módulo addon-empresa.
 *
 * - GET /empresa/manual → Manual General de la Empresa (documento autocontenido)
 *
 * Cargadas por BaseModuleServiceProvider::boot vía loadRoutesFrom, por lo que
 * los grupos `web` / `auth` / `check_route_permission` se aplican explícitamente.
 */

Route::middleware(['web', 'auth', 'check_route_permission'])
    ->group(function () {
        Route::get('/empresa/manual', [EmpresaManualController::class, 'view'])->name('empresa.manual');
    });
