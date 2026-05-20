<?php

use App\Modules\Addons\Planes\Controllers\BundleController;
use App\Modules\Addons\Planes\Controllers\CustomController;
use App\Modules\Addons\Planes\Controllers\InternetController;
use App\Modules\Addons\Planes\Controllers\VozController;
use Illuminate\Support\Facades\Route;

/*
 * Rutas del módulo addon-planes.
 *
 * Catálogo de planes/servicios: Internet, Paquetes (bundles), Voz, Custom.
 *
 * URL prefixes preservados sin wrapper de grupo (las rutas legacy estaban
 * directamente bajo el grupo Module sin prefix de módulo).
 *
 * `web` necesario porque loadRoutesFrom no aplica el grupo automáticamente.
 * `check_route_permission` replica el gating legacy.
 */

Route::middleware(['web', 'auth', 'check_route_permission'])->group(function () {

    Route::prefix('internet')->group(function () {
        Route::get('/', [InternetController::class, 'index'])->name('internet');
        Route::get('/success/{id}', [InternetController::class, 'success']);
        Route::get('/crear', [InternetController::class, 'create']);
        Route::post('/add', [InternetController::class, 'store']);
        Route::get('/editar/{id}', [InternetController::class, 'edit']);
        Route::post('/update/{id}', [InternetController::class, 'update']);
        Route::post('/destroy/{id}', [InternetController::class, 'destroy']);
        Route::post('/table', [InternetController::class, 'table']);
    });

    Route::prefix('paquetes')->group(function () {
        Route::get('/', [BundleController::class, 'index'])->name('paquetes');
        Route::get('/success/{id}', [BundleController::class, 'success']);
        Route::get('/crear', [BundleController::class, 'create']);
        Route::post('/add', [BundleController::class, 'store']);
        Route::get('/editar/{id}', [BundleController::class, 'edit']);
        Route::post('/update/{id}', [BundleController::class, 'update']);
        Route::post('/destroy/{id}', [BundleController::class, 'destroy']);
        Route::post('/table', [BundleController::class, 'table']);
    });

    Route::prefix('voz')->group(function () {
        Route::get('/', [VozController::class, 'index'])->name('voz');
        Route::get('/success/{id}', [VozController::class, 'success']);
        Route::get('/crear', [VozController::class, 'create']);
        Route::post('/add', [VozController::class, 'store']);
        Route::get('/editar/{id}', [VozController::class, 'edit']);
        Route::post('/update/{id}', [VozController::class, 'update']);
        Route::post('/destroy/{id}', [VozController::class, 'destroy']);
        Route::post('/table', [VozController::class, 'table']);
    });

    Route::prefix('custom')->group(function () {
        Route::get('/', [CustomController::class, 'index'])->name('recurrente');
        Route::get('/success/{id}', [CustomController::class, 'success']);
        Route::get('/crear', [CustomController::class, 'create']);
        Route::post('/add', [CustomController::class, 'store']);
        Route::get('/editar/{id}', [CustomController::class, 'edit']);
        Route::post('/update/{id}', [CustomController::class, 'update']);
        Route::post('/destroy/{id}', [CustomController::class, 'destroy']);
        Route::post('/table', [CustomController::class, 'table']);
    });
});
