<?php

use App\Modules\Addons\DevTools\Controllers\DevToolsController;
use Illuminate\Support\Facades\Route;

/*
 * Rutas del módulo addon-devtools.
 *
 * - /devtools           página standalone (sin sidebar/topbar) con
 *                       Claude chat + ttyd iframe.
 * - /devtools/chat      backend del chat (POST con historial).
 *
 * Sólo accesible al rol DESARROLLADOR (Spatie). `web` necesario porque
 * loadRoutesFrom() no aplica el grupo automáticamente —
 * memory/feedback_module_routes_web_middleware.md.
 *
 * NO se incluye `check_route_permission` para que cualquier DESARROLLADOR
 * pueda entrar sin permisos por URL adicionales.
 */

Route::middleware(['web', 'auth', 'role:DESARROLLADOR'])
    ->prefix('devtools')
    ->group(function () {
        Route::get('/', [DevToolsController::class, 'index'])->name('devtools.index');
        Route::post('/chat', [DevToolsController::class, 'chat'])->name('devtools.chat');
    });
