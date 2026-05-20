<?php

use App\Modules\Core\Auditoria\Controllers\ActivityLogController;
use Illuminate\Support\Facades\Route;

/*
 * Rutas del módulo core-auditoria.
 *
 * Activity log del sistema (spatie/laravel-activitylog).
 * URL prefix `administracion/activity_log/*` preservado para compat.
 *
 * `web` necesario porque loadRoutesFrom no aplica el grupo automáticamente.
 * `check_route_permission` replica el gating legacy.
 */

Route::middleware(['web', 'auth', 'check_route_permission'])
    ->prefix('administracion/activity_log')
    ->group(function () {
        Route::get('/', [ActivityLogController::class, 'index']);
        Route::post('/table', [ActivityLogController::class, 'table']);
    });
