<?php

use App\Modules\Addons\Flotas\Controllers\FleetVehicleController;
use App\Modules\Addons\Flotas\Controllers\FleetMaintenanceController;
use App\Modules\Addons\Flotas\Controllers\FleetDocumentController;
use App\Modules\Addons\Flotas\Controllers\FleetProviderController;
use App\Modules\Addons\Flotas\Controllers\FleetFuelLogController;
use App\Modules\Addons\Flotas\Controllers\FleetPhotoController;
use App\Modules\Addons\Flotas\Controllers\FleetAssignmentController;
use App\Modules\Addons\Flotas\Controllers\FleetGpsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'check_route_permission'])
    ->prefix('flotas')
    ->group(function () {

        // ── Vistas web ─────────────────────────────────────────────────────────
        // NOTA: `/nuevo` debe declararse ANTES de `/{id}` para que no lo capture como id.
        Route::get('/',           fn() => view('addon-flotas::flotas.index'));
        Route::get('/vehiculos',  fn() => view('addon-flotas::flotas.index'));
        Route::get('/nuevo',      fn() => view('addon-flotas::flotas.create'));
        Route::get('/mapa',       fn() => view('addon-flotas::flotas.mapa')); // ANTES de /{id}
        Route::get('/{id}',       fn() => view('addon-flotas::flotas.show'));

        // ── API: Vehículos ─────────────────────────────────────────────────────
        Route::prefix('api/vehiculos')->group(function () {
            Route::get('/',          [FleetVehicleController::class, 'index']);
            Route::post('/',         [FleetVehicleController::class, 'store']);

            // Datos auxiliares (segmentos /data/* → no chocan con /{id})
            Route::get('/data/dashboard',  [FleetVehicleController::class, 'dashboard']);
            Route::get('/data/alertas',    [FleetVehicleController::class, 'alertas']);
            Route::get('/data/operadores', [FleetVehicleController::class, 'operators']);

            // Asignaciones del vehículo
            Route::get('/{id}/asignaciones',  [FleetAssignmentController::class, 'index']);
            Route::post('/{id}/asignaciones', [FleetAssignmentController::class, 'store']);

            // Tracking GPS del vehículo (Fase 2)
            Route::get('/{id}/gps',          [FleetGpsController::class, 'status']);
            Route::get('/{id}/gps/history',  [FleetGpsController::class, 'history']);
            Route::post('/{id}/gps/device',  [FleetGpsController::class, 'activateDevice']);

            Route::get('/{id}',      [FleetVehicleController::class, 'show']);
            Route::patch('/{id}',    [FleetVehicleController::class, 'update']);
            Route::delete('/{id}',   [FleetVehicleController::class, 'destroy']);
            Route::post('/{id}/restore', [FleetVehicleController::class, 'restore']);
        });

        // ── API: GPS / Flota en mapa ───────────────────────────────────────────
        Route::prefix('api/gps')->group(function () {
            Route::get('/flota', [FleetGpsController::class, 'fleet']);
        });

        // ── API: Mantenimientos ────────────────────────────────────────────────
        Route::prefix('api/mantenimientos')->group(function () {
            Route::get('/',        [FleetMaintenanceController::class, 'index']);
            Route::post('/',       [FleetMaintenanceController::class, 'store']);
            Route::get('/{id}',    [FleetMaintenanceController::class, 'show']);
            Route::patch('/{id}',  [FleetMaintenanceController::class, 'update']);
            Route::delete('/{id}', [FleetMaintenanceController::class, 'destroy']);
            Route::post('/{id}/files', [FleetMaintenanceController::class, 'uploadFile']);
        });

        // ── API: Documentos ────────────────────────────────────────────────────
        Route::prefix('api/documentos')->group(function () {
            Route::get('/',        [FleetDocumentController::class, 'index']);
            Route::post('/',       [FleetDocumentController::class, 'store']);
            Route::get('/{id}',    [FleetDocumentController::class, 'show']);
            Route::patch('/{id}',  [FleetDocumentController::class, 'update']);
            Route::delete('/{id}', [FleetDocumentController::class, 'destroy']);
            Route::get('/alertas/proximos', [FleetDocumentController::class, 'proximos']);
        });

        // ── API: Proveedores ───────────────────────────────────────────────────
        Route::prefix('api/proveedores')->group(function () {
            Route::get('/',        [FleetProviderController::class, 'index']);
            Route::post('/',       [FleetProviderController::class, 'store']);
            Route::patch('/{id}',  [FleetProviderController::class, 'update']);
            Route::delete('/{id}', [FleetProviderController::class, 'destroy']);
        });

        // ── API: Combustible ───────────────────────────────────────────────────
        Route::prefix('api/combustible')->group(function () {
            Route::get('/',        [FleetFuelLogController::class, 'index']);
            Route::post('/',       [FleetFuelLogController::class, 'store']);
            Route::delete('/{id}', [FleetFuelLogController::class, 'destroy']);
        });

        // ── API: Fotos ─────────────────────────────────────────────────────────
        Route::prefix('api/fotos')->group(function () {
            Route::post('/',       [FleetPhotoController::class, 'store']);
            Route::delete('/{id}', [FleetPhotoController::class, 'destroy']);
        });
    });
