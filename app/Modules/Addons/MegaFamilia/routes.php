<?php

use App\Modules\Addons\MegaFamilia\Controllers\AlertasController;
use App\Modules\Addons\MegaFamilia\Controllers\ApiController;
use App\Modules\Addons\MegaFamilia\Controllers\AuditoriaController;
use App\Modules\Addons\MegaFamilia\Controllers\ClientesController;
use App\Modules\Addons\MegaFamilia\Controllers\ConfiguracionController;
use App\Modules\Addons\MegaFamilia\Controllers\DashboardController;
use App\Modules\Addons\MegaFamilia\Controllers\DispositivosController;
use App\Modules\Addons\MegaFamilia\Controllers\IngresosController;
use App\Modules\Addons\MegaFamilia\Controllers\LicenciasController;
use App\Modules\Addons\MegaFamilia\Controllers\MikrotikController;
use App\Modules\Addons\MegaFamilia\Controllers\NotificacionesController;
use App\Modules\Addons\MegaFamilia\Controllers\PerfilesController;
use App\Modules\Addons\MegaFamilia\Controllers\PlanesController;
use App\Modules\Addons\MegaFamilia\Controllers\ReportesController;
use App\Modules\Addons\MegaFamilia\Controllers\SolicitudesController;
use App\Modules\Addons\MegaFamilia\Controllers\SoporteController;
use App\Modules\Addons\MegaFamilia\Controllers\TareasController;
use App\Modules\Addons\MegaFamilia\Controllers\TerminosController;
use App\Modules\Addons\MegaFamilia\Controllers\UbicacionesController;
use Illuminate\Support\Facades\Route;

/*
 * Rutas del módulo addon-megafamilia.
 *
 * - UI web bajo /megafamilia (web + auth + permission por sección).
 * - API mobile bajo /api/megafamilia (sanctum).
 *
 * `web` es necesario en el grupo principal porque loadRoutesFrom no
 * aplica el grupo `web` automáticamente — ver
 * memory/feedback_module_routes_web_middleware.md.
 */

// ---------------------------------------------------------------------------
// UI WEB
// ---------------------------------------------------------------------------

Route::middleware(['web', 'auth'])->prefix('megafamilia')->group(function () {

    // ----- Sólo admin (permission: megafamilia_admin) -----
    Route::middleware('permission:megafamilia_admin')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('megafamilia.index');
        Route::get('/dashboard/summary', [DashboardController::class, 'summary']);

        Route::prefix('clientes')->group(function () {
            Route::get('/', [ClientesController::class, 'index']);
            Route::get('/data', [ClientesController::class, 'data']);
            Route::get('/{id}', [ClientesController::class, 'show'])->whereNumber('id');
            Route::post('/{id}/activate', [ClientesController::class, 'activate'])->whereNumber('id');
            Route::post('/{id}/suspend', [ClientesController::class, 'suspend'])->whereNumber('id');
        });

        Route::prefix('licencias')->group(function () {
            Route::get('/', [LicenciasController::class, 'index']);
            Route::get('/data', [LicenciasController::class, 'data']);
            Route::post('/', [LicenciasController::class, 'store']);
            Route::post('/{id}/renew', [LicenciasController::class, 'renew'])->whereNumber('id');
            Route::post('/{id}/suspend', [LicenciasController::class, 'suspend'])->whereNumber('id');
        });

        Route::prefix('planes')->group(function () {
            Route::get('/', [PlanesController::class, 'index']);
            Route::get('/data', [PlanesController::class, 'data']);
            Route::post('/{id}', [PlanesController::class, 'update'])->whereNumber('id');
        });

        Route::prefix('ingresos')->group(function () {
            Route::get('/', [IngresosController::class, 'index']);
            Route::get('/data', [IngresosController::class, 'data']);
            Route::get('/export', [IngresosController::class, 'export']);
        });

        Route::prefix('notificaciones')->group(function () {
            Route::get('/', [NotificacionesController::class, 'index']);
            Route::get('/history', [NotificacionesController::class, 'history']);
            Route::post('/send', [NotificacionesController::class, 'send']);
        });

        Route::prefix('configuracion')->group(function () {
            Route::get('/', [ConfiguracionController::class, 'index']);
            Route::get('/get', [ConfiguracionController::class, 'get']);
            Route::post('/update', [ConfiguracionController::class, 'update']);
        });

        Route::prefix('terminos')->group(function () {
            Route::get('/', [TerminosController::class, 'index']);
            Route::get('/data', [TerminosController::class, 'data']);
        });

        Route::prefix('auditoria')->group(function () {
            Route::get('/', [AuditoriaController::class, 'index']);
            Route::get('/data', [AuditoriaController::class, 'data']);
        });

        Route::prefix('mikrotik')->group(function () {
            Route::get('/', [MikrotikController::class, 'index']);
            Route::get('/get', [MikrotikController::class, 'get']);
            Route::post('/update', [MikrotikController::class, 'update']);
            Route::post('/test', [MikrotikController::class, 'testConnection']);
        });
    });

    // ----- Admin + soporte (permission: megafamilia_support OR megafamilia_admin) -----
    Route::middleware('permission:megafamilia_admin|megafamilia_support')->group(function () {
        Route::prefix('alertas')->group(function () {
            Route::get('/', [AlertasController::class, 'index']);
            Route::get('/data', [AlertasController::class, 'data']);
            Route::post('/{id}/read', [AlertasController::class, 'markRead'])->whereNumber('id');
        });

        Route::prefix('solicitudes')->group(function () {
            Route::get('/', [SolicitudesController::class, 'index']);
            Route::get('/data', [SolicitudesController::class, 'data']);
            Route::post('/{id}/approve', [SolicitudesController::class, 'approve'])->whereNumber('id');
            Route::post('/{id}/reject', [SolicitudesController::class, 'reject'])->whereNumber('id');
        });

        Route::prefix('dispositivos')->group(function () {
            Route::get('/', [DispositivosController::class, 'index']);
            Route::get('/data', [DispositivosController::class, 'data']);
            Route::get('/{id}', [DispositivosController::class, 'show'])->whereNumber('id');
        });

        Route::prefix('ubicaciones')->group(function () {
            Route::get('/', [UbicacionesController::class, 'index']);
            Route::get('/latest', [UbicacionesController::class, 'latest']);
        });

        Route::prefix('soporte')->group(function () {
            Route::get('/', [SoporteController::class, 'index']);
            Route::get('/data', [SoporteController::class, 'data']);
            Route::get('/{id}', [SoporteController::class, 'show'])->whereNumber('id');
            Route::post('/{id}/respond', [SoporteController::class, 'respond'])->whereNumber('id');
        });
    });

    // ----- Cliente final (solo auth, sin permission extra) -----
    Route::prefix('perfiles')->group(function () {
        Route::get('/', [PerfilesController::class, 'index']);
        Route::get('/data', [PerfilesController::class, 'data']);
        Route::post('/', [PerfilesController::class, 'store']);
        Route::put('/{id}', [PerfilesController::class, 'update'])->whereNumber('id');
        Route::delete('/{id}', [PerfilesController::class, 'destroy'])->whereNumber('id');
    });

    Route::prefix('tareas')->group(function () {
        Route::get('/', [TareasController::class, 'index']);
        Route::get('/data', [TareasController::class, 'data']);
        Route::post('/', [TareasController::class, 'store']);
        Route::post('/{id}/approve', [TareasController::class, 'approve'])->whereNumber('id');
        Route::post('/{id}/reject', [TareasController::class, 'reject'])->whereNumber('id');
    });

    Route::prefix('reportes')->group(function () {
        Route::get('/', [ReportesController::class, 'index']);
        Route::get('/data', [ReportesController::class, 'data']);
        Route::get('/export', [ReportesController::class, 'export']);
    });
});

// ---------------------------------------------------------------------------
// API MOBILE
// ---------------------------------------------------------------------------

Route::prefix('api/megafamilia')->middleware('log_api_mobile')->group(function () {
    // Public: login → returns sanctum token
    Route::post('/auth/login', [ApiController::class, 'login']);

    // Public: OTA — la app revisa esto al abrir y se autoactualiza
    Route::get('/app-version', [ApiController::class, 'appVersion']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/account', [ApiController::class, 'account']);

        // Cliente dashboard (mobile home)
        Route::get('/servicio', [ApiController::class, 'servicio']);
        Route::get('/tickets', [ApiController::class, 'tickets']);
        Route::post('/tickets', [ApiController::class, 'storeTicket']);
        Route::get('/profile', [ApiController::class, 'profile']);
        Route::get('/facturas', [ApiController::class, 'facturas']);
        Route::get('/pagos', [ApiController::class, 'pagos']);
        Route::post('/pagos', [ApiController::class, 'crearPago']);

        Route::get('/profiles', [ApiController::class, 'profiles']);
        Route::post('/profiles', [ApiController::class, 'storeProfile']);
        Route::get('/profiles/{id}/devices', [ApiController::class, 'profileDevices'])->whereNumber('id');
        Route::get('/profiles/{id}/tasks', [ApiController::class, 'profileTasks'])->whereNumber('id');
        Route::get('/profiles/{id}/location', [ApiController::class, 'profileLocation'])->whereNumber('id');

        Route::post('/devices/link', [ApiController::class, 'linkDevice']);
        Route::get('/devices/{id}/rules', [ApiController::class, 'deviceRules'])->whereNumber('id');
        Route::put('/devices/{id}/rules', [ApiController::class, 'updateDeviceRules'])->whereNumber('id');

        Route::post('/tasks/{id}/complete', [ApiController::class, 'completeTask'])->whereNumber('id');

        Route::post('/requests', [ApiController::class, 'storeRequest']);
        Route::get('/requests/pending', [ApiController::class, 'pendingRequests']);
        Route::post('/requests/{id}/respond', [ApiController::class, 'respondRequest'])->whereNumber('id');

        Route::post('/locations', [ApiController::class, 'reportLocation']);
    });
});
