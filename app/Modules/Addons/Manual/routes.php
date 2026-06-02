<?php

use App\Modules\Addons\Manual\Controllers\ManualController;
use Illuminate\Support\Facades\Route;

/*
 * Rutas del módulo addon-manual.
 *
 * - GET  /manual                       → vista Blade que monta ManualIndex.vue
 * - GET  /api/manual/sections          → lista todas las secciones agrupadas por módulo
 * - GET  /api/manual/sections/{slug}   → sección individual
 * - GET  /api/manual/help              → ayuda contextual de la pantalla actual (panel lateral)
 * - POST /api/manual/generate          → regenera el manual vía Claude API (rol DESARROLLADOR)
 *
 * Cargadas por BaseModuleServiceProvider::boot vía loadRoutesFrom, por lo que
 * los grupos `web` / `auth` / `check_route_permission` se aplican explícitamente.
 */

Route::middleware(['web', 'auth', 'check_route_permission'])
    ->group(function () {
        Route::get('/manual', [ManualController::class, 'view'])->name('manual.index');
    });

Route::middleware(['web', 'auth', 'check_route_permission'])
    ->prefix('api/manual')
    ->group(function () {
        Route::get('/sections', [ManualController::class, 'index']);
        Route::get('/sections/{slug}', [ManualController::class, 'show']);
        Route::post('/generate', [ManualController::class, 'generate']);
    });

// Ayuda contextual: disponible en TODAS las pantallas para CUALQUIER usuario
// autenticado, por eso sólo lleva `web, auth` (sin check_route_permission).
Route::middleware(['web', 'auth'])
    ->prefix('api/manual')
    ->group(function () {
        Route::get('/help', [ManualController::class, 'help']);
    });
