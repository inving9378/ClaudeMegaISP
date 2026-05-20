<?php

use App\Modules\Addons\DevTools\Controllers\DevToolsController;
use App\Modules\Addons\DevTools\Controllers\Git\GitController;
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
        Route::get('/context', [DevToolsController::class, 'context'])->name('devtools.context');
        Route::post('/chat', [DevToolsController::class, 'chat'])->name('devtools.chat');
    });

// Git tooling — sub-namespace dentro de DevTools, pero gating con
// `check_route_permission` (NO role:DESARROLLADOR) para preservar la
// política legacy: la pestaña de Releases consume /git/get-tags y no
// necesariamente requiere DESARROLLADOR.
Route::middleware(['web', 'auth', 'check_route_permission'])
    ->prefix('git')
    ->group(function () {
        Route::get('/get-tags', [GitController::class, 'getTags']);
    });
