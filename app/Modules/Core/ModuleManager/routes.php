<?php

use App\Modules\Core\ModuleManager\Controllers\ModuleManagerController;
use Illuminate\Support\Facades\Route;

/*
 * Rutas del módulo Core/ModuleManager.
 *
 * UI de administración de módulos en /admin/modules. Sólo accesible a
 * usuarios con la permission `admin_modules` (Spatie).
 *
 * `web` necesario porque loadRoutesFrom() no aplica el grupo automáticamente —
 * memory/feedback_module_routes_web_middleware.md.
 */

Route::middleware(['web', 'auth', 'check_route_permission'])
    ->prefix('admin/modules')
    ->group(function () {
        Route::get('/', [ModuleManagerController::class, 'index'])->name('admin.modules.index');
        Route::post('/{slug}/migrate', [ModuleManagerController::class, 'migrate'])->name('admin.modules.migrate');
        Route::post('/{slug}/toggle', [ModuleManagerController::class, 'toggle'])->name('admin.modules.toggle');
        Route::get('/{slug}/history', [ModuleManagerController::class, 'history'])->name('admin.modules.history');
    });
