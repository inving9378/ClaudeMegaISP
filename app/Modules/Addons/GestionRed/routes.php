<?php

use App\Modules\Addons\GestionRed\Controllers\Network\Ipv4CalculatorController;
use App\Modules\Addons\GestionRed\Controllers\Network\NetworkController;
use App\Modules\Addons\GestionRed\Controllers\Network\NetworkIpController;
use Illuminate\Support\Facades\Route;

/*
 * Rutas del módulo addon-gestion-red.
 *
 * Agrupa Network (IPv4), Router (Mikrotik) y OLTs.
 *
 * `web` necesario porque loadRoutesFrom no aplica el grupo automáticamente.
 * `check_route_permission` replica el gating legacy (CheckRoutePermission).
 */

Route::middleware(['web', 'auth', 'check_route_permission'])->prefix('red')->group(function () {

    // ---------------------------------------------------------------
    // IPv4 (Network) — migrado de routes/web.php (namespace Network)
    // ---------------------------------------------------------------
    Route::prefix('ipv4')->group(function () {
        Route::post('/add', [NetworkController::class, 'store']);
        Route::get('/listar', [NetworkController::class, 'index']);
        Route::get('/success', [NetworkController::class, 'success']);
        Route::get('/crear', [NetworkController::class, 'create']);
        Route::post('/table', [NetworkController::class, 'table']);
        Route::post('/update/{id}', [NetworkController::class, 'update']);
        Route::post('/destroy/{id}', [NetworkController::class, 'destroy']);
        Route::post('/network/{id}', [NetworkController::class, 'getIpByNetwork']);

        Route::get('/ver/{id}', [NetworkIpController::class, 'show']);
        Route::post('/ip/table', [NetworkIpController::class, 'table']);
        Route::post('/ip/update/{id}', [NetworkIpController::class, 'update']);

        Route::post('/calculator', [Ipv4CalculatorController::class, 'calculator']);
    });

});
