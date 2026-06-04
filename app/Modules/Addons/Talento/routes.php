<?php

use App\Modules\Addons\Talento\Controllers\TalentoColaboradorController;
use App\Modules\Addons\Talento\Controllers\TalentoCustodiaController;
use App\Modules\Addons\Talento\Controllers\TalentoDeviceController;
use App\Modules\Addons\Talento\Controllers\TalentoRoadmapController;
use App\Modules\Addons\Talento\Controllers\TalentoWorkOrderController;
use App\Modules\Addons\Talento\Controllers\TalentoCompensacionController;
use App\Modules\Addons\Talento\Controllers\TalentoLiquidacionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'check_route_permission'])
    ->prefix('talento')
    ->group(function () {

        // ── Vistas web (ANTES de rutas con parámetros) ──────────────────────
        Route::get('/',               [TalentoColaboradorController::class,  'index']);
        Route::get('/custodia',       [TalentoCustodiaController::class,     'index']);
        Route::get('/dispositivos',   [TalentoDeviceController::class,       'index']);
        Route::get('/roadmap',        [TalentoRoadmapController::class,      'index']);
        Route::get('/ordenes',        [TalentoWorkOrderController::class,    'index']);
        Route::get('/compensacion',   [TalentoCompensacionController::class, 'index']);
        Route::get('/liquidaciones',  [TalentoLiquidacionController::class,  'index']);

        // ── API JSON ─────────────────────────────────────────────────────────
        Route::prefix('api')->group(function () {

            // ── Colaboradores ────────────────────────────────────────────────
            Route::get('/colaboradores',                   [TalentoColaboradorController::class, 'data']);
            Route::post('/colaboradores',                  [TalentoColaboradorController::class, 'store']);
            Route::get('/colaboradores/users-disponibles', [TalentoColaboradorController::class, 'usersDisponibles']);
            Route::get('/colaboradores/{id}',              [TalentoColaboradorController::class, 'show']);
            Route::put('/colaboradores/{id}',              [TalentoColaboradorController::class, 'update']);
            Route::delete('/colaboradores/{id}',           [TalentoColaboradorController::class, 'destroy']);

            // ── Custodia (solo lectura) ───────────────────────────────────────
            Route::get('/colaboradores/{id}/custodia',     [TalentoCustodiaController::class, 'show']);

            // ── Dispositivos ─────────────────────────────────────────────────
            Route::get('/colaboradores/{id}/dispositivos',                    [TalentoDeviceController::class, 'forColaborador']);
            Route::post('/colaboradores/{id}/dispositivos',                   [TalentoDeviceController::class, 'bind']);
            Route::post('/colaboradores/{id}/dispositivos/{devId}/approve',   [TalentoDeviceController::class, 'approve']);
            Route::delete('/colaboradores/{id}/dispositivos/{devId}',         [TalentoDeviceController::class, 'revoke']);

            // ── Compensación — regla del colaborador ──────────────────────────
            Route::post('/colaboradores/{id}/regla',       [TalentoCompensacionController::class, 'assignRule']);
            Route::get('/colaboradores/{id}/regla',        [TalentoCompensacionController::class, 'currentRule']);
            Route::get('/colaboradores/{id}/regla/historial', [TalentoCompensacionController::class, 'historyForColaborador']);

            // ── Avance semanal (endpoint para app) ───────────────────────────
            Route::get('/colaboradores/{id}/avance',       [TalentoLiquidacionController::class, 'avance']);

            // ── Roadmap ───────────────────────────────────────────────────────
            Route::get('/roadmap',        [TalentoRoadmapController::class, 'data']);
            Route::put('/roadmap/{id}',   [TalentoRoadmapController::class, 'update']);

            // ── Tipos de orden ────────────────────────────────────────────────
            Route::get('/order-types',       [TalentoWorkOrderController::class, 'types']);
            Route::post('/order-types',      [TalentoWorkOrderController::class, 'storeType']);
            Route::put('/order-types/{id}',  [TalentoWorkOrderController::class, 'updateType']);

            // ── Órdenes de trabajo ────────────────────────────────────────────
            Route::get('/ordenes',                          [TalentoWorkOrderController::class, 'data']);
            Route::post('/ordenes',                         [TalentoWorkOrderController::class, 'store']);
            Route::get('/ordenes/{id}',                     [TalentoWorkOrderController::class, 'show']);
            Route::put('/ordenes/{id}',                     [TalentoWorkOrderController::class, 'update']);
            Route::put('/ordenes/{id}/status',              [TalentoWorkOrderController::class, 'changeStatus']);
            Route::post('/ordenes/{id}/validate',           [TalentoWorkOrderController::class, 'validate']);
            Route::post('/ordenes/{id}/actividades',        [TalentoWorkOrderController::class, 'addActivity']);

            // ── Reglas de compensación ────────────────────────────────────────
            Route::get('/reglas',        [TalentoCompensacionController::class, 'rules']);
            Route::post('/reglas',       [TalentoCompensacionController::class, 'storeRule']);
            Route::put('/reglas/{id}',   [TalentoCompensacionController::class, 'updateRule']);

            // ── Liquidaciones ─────────────────────────────────────────────────
            Route::get('/liquidaciones',          [TalentoLiquidacionController::class, 'data']);
            Route::post('/liquidaciones/calcular',[TalentoLiquidacionController::class, 'calcular']);
            Route::get('/liquidaciones/{id}',     [TalentoLiquidacionController::class, 'show']);
            Route::post('/liquidaciones/{id}/cerrar', [TalentoLiquidacionController::class, 'cerrar']);
        });
    });
