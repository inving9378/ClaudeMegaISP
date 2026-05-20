<?php

use App\Modules\Core\Localizacion\Controllers\ColonyController;
use App\Modules\Core\Localizacion\Controllers\LocationController;
use App\Modules\Core\Localizacion\Controllers\MunicipalityController;
use App\Modules\Core\Localizacion\Controllers\StateController;
use App\Modules\Core\Localizacion\Controllers\SucursalController;
use Illuminate\Support\Facades\Route;

/*
 * Rutas del módulo core-localizacion.
 *
 * Estado, Municipio, Colonia, Sucursal, Ubicación.
 *
 * URL prefix `administracion/*` preservado para compatibilidad con frontend.
 *
 * `web` necesario porque loadRoutesFrom no aplica el grupo automáticamente.
 * `check_route_permission` replica el gating legacy.
 */

Route::middleware(['web', 'auth', 'check_route_permission'])->prefix('administracion')->group(function () {

    Route::prefix('ubicacion')->group(function () {
        Route::get('/', [LocationController::class, 'index']);
        Route::post('/add', [LocationController::class, 'store']);
        Route::get('/editar/{id}', [LocationController::class, 'edit']);
        Route::post('/update/{id}', [LocationController::class, 'update']);
        Route::post('/destroy/{id}', [LocationController::class, 'destroy']);
        Route::post('/table', [LocationController::class, 'table']);
    });

    Route::prefix('sucursal')->group(function () {
        Route::get('/', [SucursalController::class, 'index']);
        Route::get('/all', [SucursalController::class, 'all']);
        Route::post('/add', [SucursalController::class, 'store']);
        Route::get('/editar/{id}', [SucursalController::class, 'edit']);
        Route::post('/update/{id}', [SucursalController::class, 'update']);
        Route::post('/destroy/{id}', [SucursalController::class, 'destroy']);
        Route::post('/table', [SucursalController::class, 'table']);
    });

    Route::prefix('estado')->group(function () {
        Route::get('/', [StateController::class, 'index']);
        Route::post('/add', [StateController::class, 'store']);
        Route::get('/editar/{id}', [StateController::class, 'edit']);
        Route::post('/update/{id}', [StateController::class, 'update']);
        Route::post('/destroy/{id}', [StateController::class, 'destroy']);
        Route::post('/table', [StateController::class, 'table']);
    });

    Route::prefix('municipio')->group(function () {
        Route::get('/', [MunicipalityController::class, 'index']);
        Route::post('/add', [MunicipalityController::class, 'store']);
        Route::get('/editar/{id}', [MunicipalityController::class, 'edit']);
        Route::post('/update/{id}', [MunicipalityController::class, 'update']);
        Route::post('/destroy/{id}', [MunicipalityController::class, 'destroy']);
        Route::post('/table', [MunicipalityController::class, 'table']);
    });

    Route::prefix('colonia')->group(function () {
        Route::get('/', [ColonyController::class, 'index']);
        Route::post('/add', [ColonyController::class, 'store']);
        Route::get('/editar/{id}', [ColonyController::class, 'edit']);
        Route::post('/update/{id}', [ColonyController::class, 'update']);
        Route::post('/destroy/{id}', [ColonyController::class, 'destroy']);
        Route::post('/table', [ColonyController::class, 'table']);
    });
});

// Helper utility consumido por formularios de varios módulos (resolver de
// catálogos geo state/municipality/colony). Ruta global sin prefix por compat.
Route::middleware(['web', 'auth', 'check_route_permission'])->group(function () {
    Route::post('/helper/get-value-colony-state-municipality', [\App\Modules\Core\Localizacion\Controllers\ComponentSelectStateMunicipalityAndColonyController::class, 'getValueDB']);
});
