<?php

use App\Modules\Addons\Flotas\Controllers\FleetVehicleController;
use App\Modules\Addons\Flotas\Controllers\FleetMaintenanceController;
use App\Modules\Addons\Flotas\Controllers\FleetDocumentController;
use App\Modules\Addons\Flotas\Controllers\FleetProviderController;
use App\Modules\Addons\Flotas\Controllers\FleetFuelLogController;
use App\Modules\Addons\Flotas\Controllers\FleetPhotoController;
use App\Modules\Addons\Flotas\Controllers\FleetAssignmentController;
use App\Modules\Addons\Flotas\Controllers\FleetGpsController;
use App\Modules\Addons\Flotas\Controllers\FleetGeofenceController;
use App\Modules\Addons\Flotas\Controllers\FleetNotificationController;
use App\Modules\Addons\Flotas\Controllers\FleetRuleController;
use App\Modules\Addons\Flotas\Controllers\FleetSubscriptionController;
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

        // Geocercas (Sub-fase 3.1) — TODAS antes de /{id} para que no las capture como id.
        Route::get('/geocercas',              fn() => view('addon-flotas::flotas.geocercas.index'));
        Route::get('/geocercas/nueva',        fn() => view('addon-flotas::flotas.geocercas.form'));
        Route::get('/geocercas/{id}/editar',  fn() => view('addon-flotas::flotas.geocercas.form'));
        Route::get('/geocercas/{id}',         fn() => view('addon-flotas::flotas.geocercas.show'));

        // Log de notificaciones (Sub-fase 3.3) — antes de /{id}.
        Route::get('/notificaciones-log',     fn() => view('addon-flotas::flotas.notificaciones'));

        // Reglas de alertas (Sub-fase 3.4) — antes de /{id}.
        Route::get('/reglas',                 fn() => view('addon-flotas::flotas.reglas'));

        // Dashboard global de documentos (Sub-fase 4.1b) — antes de /{id}.
        Route::get('/documentos',             fn() => view('addon-flotas::flotas.documentos'));

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
            Route::get('/{id}/geofence-events', [FleetGpsController::class, 'geofenceEvents']);

            // Preferencias de alerta del usuario (Sub-fase 3.3)
            Route::get('/{id}/notification-preference',  [FleetNotificationController::class, 'myPreference']);
            Route::post('/{id}/notification-preference', [FleetNotificationController::class, 'savePreference']);

            Route::get('/{id}',      [FleetVehicleController::class, 'show']);
            Route::patch('/{id}',    [FleetVehicleController::class, 'update']);
            Route::delete('/{id}',   [FleetVehicleController::class, 'destroy']);
            Route::post('/{id}/restore', [FleetVehicleController::class, 'restore']);
        });

        // ── API: GPS / Flota en mapa ───────────────────────────────────────────
        Route::prefix('api/gps')->group(function () {
            Route::get('/flota', [FleetGpsController::class, 'fleet']);
        });

        // ── API: Log de notificaciones (Sub-fase 3.3) ───────────────────────────
        Route::prefix('api/notificaciones-log')->group(function () {
            Route::get('/',            [FleetNotificationController::class, 'log']);
            Route::post('/{id}/resend',[FleetNotificationController::class, 'resend']);
        });

        // ── API: Reglas de alertas (Sub-fase 3.4) ───────────────────────────────
        Route::prefix('api/reglas')->group(function () {
            Route::get('/',           [FleetRuleController::class, 'index']);
            Route::post('/',          [FleetRuleController::class, 'store']);
            Route::put('/{id}',       [FleetRuleController::class, 'update']);
            Route::delete('/{id}',    [FleetRuleController::class, 'destroy']);
            Route::post('/{id}/toggle',[FleetRuleController::class, 'toggle']);
        });

        // ── API: Geocercas (Sub-fase 3.1) ───────────────────────────────────────
        Route::prefix('api/geocercas')->group(function () {
            Route::get('/',        [FleetGeofenceController::class, 'index']);
            Route::post('/',       [FleetGeofenceController::class, 'store']);
            Route::get('/{id}',    [FleetGeofenceController::class, 'show']);
            Route::put('/{id}',    [FleetGeofenceController::class, 'update']);
            Route::patch('/{id}',  [FleetGeofenceController::class, 'update']);
            Route::delete('/{id}', [FleetGeofenceController::class, 'destroy']);
            Route::post('/{id}/vehiculos', [FleetGeofenceController::class, 'assignVehicles']);
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
            Route::get('/',                       [FleetDocumentController::class, 'index']);
            Route::post('/',                      [FleetDocumentController::class, 'store']);
            Route::get('/alertas/proximos',       [FleetDocumentController::class, 'proximos']);    // ANTES de /{id}
            Route::get('/dashboard/all',          [FleetDocumentController::class, 'dashboard']);   // ANTES de /{id}
            Route::post('/ocr',                   [FleetDocumentController::class, 'ocr']);         // #580 — ANTES de /{id}
            Route::post('/{id}/ocr/revisado',     [FleetDocumentController::class, 'markOcrReviewed']);
            Route::get('/{id}',                   [FleetDocumentController::class, 'show']);
            Route::post('/{id}',                  [FleetDocumentController::class, 'update']);      // multipart/form-data no admite PATCH en algunos navegadores
            Route::patch('/{id}',                 [FleetDocumentController::class, 'update']);
            Route::delete('/{id}',                [FleetDocumentController::class, 'destroy']);
            Route::get('/{id}/descargar',         [FleetDocumentController::class, 'download']);    // segmento literal — no choca con /{id}
            Route::get('/{id}/renovar/prefill',   [FleetDocumentController::class, 'renew']);       // prefill para renovación
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

        // ── Suscripciones SaaS (Fase 6) ────────────────────────────────────────
        // Vista web del dashboard de suscripciones (ANTES de la ruta API para evitar colisión)
        Route::get('suscripciones', fn () => view('addon-flotas::flotas.suscripciones.index'))
            ->middleware('permission:fleet.subscriptions.manage');

        Route::middleware('permission:fleet.subscriptions.manage')
            ->prefix('api/suscripciones')
            ->group(function () {
                Route::get('dashboard',                         [FleetSubscriptionController::class, 'index']);
                Route::get('catalog',                           [FleetSubscriptionController::class, 'catalog']);
                Route::get('client/{clientId}',                 [FleetSubscriptionController::class, 'forClient'])->whereNumber('clientId');
                Route::post('client/{clientId}/start-trial',    [FleetSubscriptionController::class, 'startTrial'])->whereNumber('clientId');
                Route::post('client/{clientId}/change-plan',    [FleetSubscriptionController::class, 'changePlan'])->whereNumber('clientId');
                Route::post('client/{clientId}/cancel',         [FleetSubscriptionController::class, 'cancel'])->whereNumber('clientId');
                // confirmPayment → NO routeado hasta Fase 6.2 (integración con módulo Pagos)
            });
    });
