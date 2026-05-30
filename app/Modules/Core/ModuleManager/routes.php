<?php

use App\Modules\Core\ModuleManager\Controllers\AdminPanelController;
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

        // Ciclo de vida
        Route::get('/{slug}/install/preview', [ModuleManagerController::class, 'installPreview'])->name('admin.modules.install.preview');
        Route::post('/{slug}/install', [ModuleManagerController::class, 'installModule'])->name('admin.modules.install');
        Route::post('/{slug}/upgrade', [ModuleManagerController::class, 'upgradeModule'])->name('admin.modules.upgrade');
        Route::get('/{slug}/uninstall/preview', [ModuleManagerController::class, 'uninstallPreview'])->name('admin.modules.uninstall.preview');
        Route::delete('/{slug}/uninstall', [ModuleManagerController::class, 'uninstallModule'])->name('admin.modules.uninstall');

        // Datos compilados por ModuleRegistry
        Route::get('/registry/data', [ModuleManagerController::class, 'registryData'])->name('admin.modules.registry');
    });

// Panel central de Administración (tarjetas de módulos)
Route::middleware(['web', 'auth', 'check_route_permission'])
    ->prefix('admin/administracion')
    ->group(function () {
        Route::get('/', [AdminPanelController::class, 'index'])->name('admin.panel.index');
        Route::get('/cards', [AdminPanelController::class, 'cards'])->name('admin.panel.cards');
    });

// API de secciones de configuración agrupadas por tipo
Route::middleware(['web', 'auth'])
    ->prefix('api/modules')
    ->group(function () {
        Route::get('/config-sections', [AdminPanelController::class, 'configSections'])->name('api.modules.config-sections');
    });
