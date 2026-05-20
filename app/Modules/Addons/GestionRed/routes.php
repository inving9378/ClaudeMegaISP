<?php

use App\Modules\Addons\GestionRed\Controllers\Network\Ipv4CalculatorController;
use App\Modules\Addons\GestionRed\Controllers\Network\NetworkController;
use App\Modules\Addons\GestionRed\Controllers\Network\NetworkIpController;
use App\Modules\Addons\GestionRed\Controllers\Router\MikrotikConfigController;
use App\Modules\Addons\GestionRed\Controllers\Router\MikrotikController;
use App\Modules\Addons\GestionRed\Controllers\Router\RouterController;
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

    // ---------------------------------------------------------------
    // Router (Mikrotik) — migrado de routes/web.php (namespace Router)
    // ---------------------------------------------------------------
    Route::prefix('router')->group(function () {
        Route::get('/listar', [RouterController::class, 'index']);
        Route::get('/success/{id}', [RouterController::class, 'success']);
        Route::get('/crear', [RouterController::class, 'create']);
        Route::post('/add', [RouterController::class, 'store']);
        Route::get('/editar/{id}', [RouterController::class, 'edit']);
        Route::post('/update/{id}', [RouterController::class, 'update']);
        Route::post('/destroy/{id}', [RouterController::class, 'destroy']);
        Route::post('/table', [RouterController::class, 'table']);

        Route::prefix('mikrotik')->group(function () {
            Route::get('/crear', [MikrotikController::class, 'create']);
            Route::post('/add', [MikrotikController::class, 'store']);
            Route::get('/editar/{id}', [MikrotikController::class, 'edit']);
            Route::post('/update/{id}', [MikrotikController::class, 'update']);
            Route::post('/crear/{id}', [MikrotikController::class, 'store']);
            Route::post('/destroy/{id}', [MikrotikController::class, 'destroy']);
            Route::post('/table', [MikrotikController::class, 'table']);
            Route::get('/cleantails', [MikrotikController::class, 'clearMikrotikTails']);
            Route::get('/read-notification/{id}', [RouterController::class, 'readNotification']);

            Route::prefix('config')->group(function () {
                Route::get('/editar/{id}', [MikrotikConfigController::class, 'edit']);
                Route::post('/update/{id}', [MikrotikConfigController::class, 'update']);
                Route::post('/crear/{id}', [MikrotikConfigController::class, 'store']);
                Route::post('/destroy/{id}', [MikrotikConfigController::class, 'destroy']);
            });
        });
    });

});

// Rutas Mikrotik globales (sin prefix `red`) — migradas de routes/web.php
Route::middleware(['web', 'auth', 'check_route_permission'])->group(function () {
    Route::post('/status-by-router/{id}', [MikrotikController::class, 'getMikrotikStatus']);
    Route::post('/remove-rules-by-router/{id}', [MikrotikController::class, 'getMikrotikRemoveRules']);
    Route::post('/create-rules-by-router/{id}', [MikrotikController::class, 'getMikrotikCreateRules']);
    Route::post('/request-clone-client-to-mikrotik/{id}', [MikrotikController::class, 'cloneClientToMikrotik']);
});
