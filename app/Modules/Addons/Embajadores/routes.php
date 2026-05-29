<?php

use App\Modules\Addons\Embajadores\Controllers\DashboardController;
use App\Modules\Addons\Embajadores\Controllers\LandingController;
use App\Modules\Addons\Embajadores\Controllers\MetricsController;
use App\Modules\Addons\Embajadores\Controllers\SettingsController;
use App\Modules\Addons\Embajadores\Controllers\VideoController;
use App\Modules\Addons\Embajadores\Controllers\TiersController;
use App\Modules\Addons\Embajadores\Controllers\ClientesController;
use App\Modules\Addons\Embajadores\Controllers\ComisionesController;
use App\Modules\Addons\Embajadores\Controllers\Api\EmbajadorApiController;
use App\Modules\Addons\Embajadores\Controllers\Api\EmbajadorExtApiController;
use App\Modules\Addons\Embajadores\Controllers\Api\ProspectsApiController;
use App\Modules\Addons\Embajadores\Controllers\Api\ProspectFollowupsApiController;
use App\Modules\Addons\Embajadores\Controllers\Api\ProspectImportApiController;
use App\Modules\Addons\Embajadores\Controllers\Api\NotificationsLogApiController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('embajadores')->group(function () {

    Route::middleware('permission:embajadores.view')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('embajadores.index');
        Route::get('/dashboard/summary', [DashboardController::class, 'summary']);

        Route::prefix('clientes')->group(function () {
            Route::get('/', [ClientesController::class, 'index']);
            Route::get('/data', [ClientesController::class, 'data']);
            Route::get('/{id}/tree', [ClientesController::class, 'tree'])->whereNumber('id');
            Route::get('/{id}', [ClientesController::class, 'show'])->whereNumber('id');
        });

        Route::prefix('metrics')->group(function () {
            Route::get('/', [MetricsController::class, 'index']);
            Route::get('/data', [MetricsController::class, 'data']);
        });

        Route::get('/video', [VideoController::class, 'index'])->name('embajadores.video');

        Route::prefix('comisiones')->group(function () {
            Route::get('/', [ComisionesController::class, 'index']);
            Route::get('/data', [ComisionesController::class, 'data']);
        });
    });

    Route::middleware('permission:embajadores.configure')->group(function () {
        Route::prefix('configuracion')->group(function () {
            Route::get('/', [SettingsController::class, 'index']);
            Route::get('/get', [SettingsController::class, 'get']);
            Route::post('/update', [SettingsController::class, 'update']);
        });

        Route::prefix('tiers')->group(function () {
            Route::get('/', [TiersController::class, 'index']);
            Route::get('/data', [TiersController::class, 'data']);
            Route::post('/', [TiersController::class, 'store']);
            Route::put('/{id}', [TiersController::class, 'update'])->whereNumber('id');
            Route::post('/{id}/toggle', [TiersController::class, 'toggle'])->whereNumber('id');
        });
    });

    Route::middleware('permission:embajadores.commissions.approve')->group(function () {
        Route::post('/comisiones/{id}/approve', [ComisionesController::class, 'approve'])->whereNumber('id');
        Route::post('/comisiones/{id}/cancel', [ComisionesController::class, 'cancel'])->whereNumber('id');
    });
});

// ── Fase 3: API móvil (Flutter) ──────────────────────────────────────────────
// Misma base URL que MegaFamilia para reutilizar el token Sanctum ya vigente.
// Endpoint público: terms — no requiere auth (se muestra en pantalla de registro).
// Resto: auth:sanctum idéntico al patrón de MegaFamilia.
Route::prefix('api/megafamilia/embajadores')->middleware('log_api_mobile')->group(function () {

    Route::get('/terms', [EmbajadorApiController::class, 'terms']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/status',    [EmbajadorApiController::class, 'status']);
        Route::post('/activate', [EmbajadorApiController::class, 'activate']);
        Route::get('/link',      [EmbajadorApiController::class, 'link']);
        Route::post('/share-log',[EmbajadorApiController::class, 'shareLog']);
        Route::get('/dashboard', [EmbajadorApiController::class, 'dashboard']);

        // ── Fase 6: Historial de notificaciones ─────────────────────────────
        Route::get('/notifications-log', [NotificationsLogApiController::class, 'index']);

        // ── Fase 7: Pantallas avanzadas ──────────────────────────────────────
        Route::get('/red',                             [EmbajadorExtApiController::class, 'red']);
        Route::get('/recompensas',                     [EmbajadorExtApiController::class, 'recompensas']);
        Route::post('/recompensas/{id}/aplicar',       [EmbajadorExtApiController::class, 'aplicarRecompensa'])->whereNumber('id');
        Route::get('/comisiones',                      [EmbajadorExtApiController::class, 'comisiones']);
        Route::post('/share-masivo',                   [EmbajadorExtApiController::class, 'shareMasivo']);

        // ── Fase 4: CRM de prospectos ────────────────────────────────────────
        // /import antes de /{id} para que "import" no sea capturado como ID.
        Route::prefix('prospects')->group(function () {
            Route::get('/',               [ProspectsApiController::class, 'index']);
            Route::post('/import',        [ProspectImportApiController::class, 'import']);
            Route::post('/',              [ProspectsApiController::class, 'store']);
            Route::get('/{id}',           [ProspectsApiController::class, 'show'])->whereNumber('id');
            Route::put('/{id}',           [ProspectsApiController::class, 'update'])->whereNumber('id');
            Route::delete('/{id}',        [ProspectsApiController::class, 'destroy'])->whereNumber('id');
            Route::get('/{id}/followups', [ProspectFollowupsApiController::class, 'index'])->whereNumber('id');
            Route::post('/{id}/followups',[ProspectFollowupsApiController::class, 'store'])->whereNumber('id');
        });
    });
});

// ── Landing pública de referidos ─────────────────────────────────────────────
// Ruta sin auth: el embajador la comparte por WhatsApp/redes.
// El visitante llega, ve la landing; el admin registra al cliente con el código.
Route::middleware('web')->group(function () {
    Route::get('/registro', [LandingController::class, 'index'])->name('embajadores.registro');
});
