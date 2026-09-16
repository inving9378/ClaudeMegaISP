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
 * Accesible a los roles DESARROLLADOR y super-administrator (Spatie). `web` necesario porque
 * loadRoutesFrom() no aplica el grupo automáticamente —
 * memory/feedback_module_routes_web_middleware.md.
 *
 * NO se incluye `check_route_permission` para que cualquier DESARROLLADOR
 * o super-administrator pueda entrar sin permisos por URL adicionales.
 */

Route::middleware(['web', 'auth', 'role:DESARROLLADOR|super-administrator'])
    ->prefix('devtools')
    ->group(function () {
        Route::get('/', [DevToolsController::class, 'index'])->name('devtools.index');
        Route::get('/context', [DevToolsController::class, 'context'])->name('devtools.context');
        Route::post('/chat', [DevToolsController::class, 'chat'])->name('devtools.chat');
        Route::get('/nav-items', [DevToolsController::class, 'navItems'])->name('devtools.nav-items');
    });

// Fase 1 ttyd-por-usuario (item #9991177): emitir el token de identidad de
// terminal para CUALQUIER usuario autenticado del panel admin, no solo
// DESARROLLADOR/super-administrator — "cada usuario del admin" en el diseño
// aprobado por Irving. Grupo aparte a propósito: el gate real de "quién puede
// ABRIR una terminal" sigue siendo el grupo de arriba (todo /devtools). El
// token por sí solo no da acceso a shell — ver DevToolsController::terminalToken().
Route::middleware(['web', 'auth'])
    ->prefix('devtools')
    ->group(function () {
        Route::get('/terminal-token', [DevToolsController::class, 'terminalToken'])->name('devtools.terminal-token');
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
