<?php

use App\Modules\Addons\EvaluadorEmpresarial\Controllers\EvaluadorEmpresarialController;
use Illuminate\Support\Facades\Route;

/*
 * Rutas del módulo addon-evaluador-empresarial.
 *
 * Cuestionario público para evaluar necesidades empresariales + dashboard staff.
 *
 * Las 4 rutas públicas (/evaluador-empresarial[/...]) deben listarse en
 * App\Http\Middleware\CheckRoutePermission::PUBLIC_ROUTES para que pasen el gating.
 *
 * `web` necesario porque loadRoutesFrom no aplica el grupo automáticamente.
 */

// ---------------------------------------------------------------
// Rutas públicas (sin auth) — listadas también en PUBLIC_ROUTES
// ---------------------------------------------------------------
Route::middleware(['web'])->group(function () {
    Route::get('/evaluador-empresarial', [EvaluadorEmpresarialController::class, 'index']);
    Route::post('/evaluador-empresarial/guardar', [EvaluadorEmpresarialController::class, 'guardar']);
    Route::post('/evaluador-empresarial/enviar-email', [EvaluadorEmpresarialController::class, 'enviarEmail']);
    Route::get('/evaluador-empresarial/resultado/{token}', [EvaluadorEmpresarialController::class, 'resultado']);
});

// ---------------------------------------------------------------
// Rutas staff (auth + check_route_permission)
// ---------------------------------------------------------------
Route::middleware(['web', 'auth', 'check_route_permission'])->prefix('admin')->group(function () {
    Route::get('/evaluaciones-panel', [EvaluadorEmpresarialController::class, 'panel']);
    Route::get('/evaluaciones', [EvaluadorEmpresarialController::class, 'listar']);
    Route::get('/evaluaciones/estadisticas', [EvaluadorEmpresarialController::class, 'estadisticas']);
    Route::get('/evaluaciones/exportar', [EvaluadorEmpresarialController::class, 'exportar']);
    Route::get('/evaluaciones/{id}', [EvaluadorEmpresarialController::class, 'mostrar'])
        ->whereNumber('id');
});
