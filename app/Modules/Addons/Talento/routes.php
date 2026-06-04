<?php

use App\Modules\Addons\Talento\Controllers\TalentoColaboradorController;
use App\Modules\Addons\Talento\Controllers\TalentoCustodiaController;
use App\Modules\Addons\Talento\Controllers\TalentoDeviceController;
use App\Modules\Addons\Talento\Controllers\TalentoRoadmapController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'check_route_permission'])
    ->prefix('talento')
    ->group(function () {

        // ── Vistas web (ANTES de rutas con parámetros) ──────────────────────
        Route::get('/',             [TalentoColaboradorController::class, 'index']);
        Route::get('/custodia',     [TalentoCustodiaController::class, 'index']);
        Route::get('/dispositivos', [TalentoDeviceController::class, 'index']);
        Route::get('/roadmap',      [TalentoRoadmapController::class, 'index']);

        // ── API JSON ─────────────────────────────────────────────────────────
        Route::prefix('api')->group(function () {

            // Colaboradores
            Route::get('/colaboradores',                    [TalentoColaboradorController::class, 'data']);
            Route::post('/colaboradores',                   [TalentoColaboradorController::class, 'store']);
            Route::get('/colaboradores/users-disponibles',  [TalentoColaboradorController::class, 'usersDisponibles']);
            Route::get('/colaboradores/{id}',               [TalentoColaboradorController::class, 'show']);
            Route::put('/colaboradores/{id}',               [TalentoColaboradorController::class, 'update']);
            Route::delete('/colaboradores/{id}',            [TalentoColaboradorController::class, 'destroy']);

            // Custodia (solo lectura)
            Route::get('/colaboradores/{id}/custodia',      [TalentoCustodiaController::class, 'show']);

            // Dispositivos
            Route::get('/colaboradores/{id}/dispositivos',              [TalentoDeviceController::class, 'forColaborador']);
            Route::post('/colaboradores/{id}/dispositivos',             [TalentoDeviceController::class, 'bind']);
            Route::post('/colaboradores/{id}/dispositivos/{devId}/approve', [TalentoDeviceController::class, 'approve']);
            Route::delete('/colaboradores/{id}/dispositivos/{devId}',   [TalentoDeviceController::class, 'revoke']);

            // Roadmap
            Route::get('/roadmap',        [TalentoRoadmapController::class, 'data']);
            Route::put('/roadmap/{id}',   [TalentoRoadmapController::class, 'update']);
        });
    });
