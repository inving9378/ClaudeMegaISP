<?php

use App\Modules\Addons\CobranzaBlaster\Controllers\CampanaController;
use App\Modules\Addons\CobranzaBlaster\Controllers\CobranzaWebhookController;
use App\Modules\Addons\CobranzaBlaster\Controllers\VoipConfiguracionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('cobranza')->group(function () {

    // ── Campañas — vistas ──────────────────────────────────────────────────────
    Route::get('/', [CampanaController::class, 'index'])
        ->name('cobranza.index');

    Route::get('/campanas', [CampanaController::class, 'index'])
        ->name('cobranza.campanas.index');

    // ── Campañas — JSON ────────────────────────────────────────────────────────
    Route::get('/campanas/data',        [CampanaController::class, 'data'])
        ->name('cobranza.campanas.data');

    Route::get('/campanas/kpis',        [CampanaController::class, 'kpis'])
        ->name('cobranza.campanas.kpis');

    Route::post('/campanas',            [CampanaController::class, 'store'])
        ->name('cobranza.campanas.store');

    Route::post('/campanas/{id}/activar', [CampanaController::class, 'activar'])
        ->name('cobranza.campanas.activar');

    Route::post('/campanas/{id}/pausar',  [CampanaController::class, 'pausar'])
        ->name('cobranza.campanas.pausar');

    Route::delete('/campanas/{id}',       [CampanaController::class, 'destroy'])
        ->name('cobranza.campanas.destroy');

    Route::get('/campanas/{id}/llamadas', [CampanaController::class, 'llamadas'])
        ->name('cobranza.campanas.llamadas');

    // ── Configuración VoIP ────────────────────────────────────────────────────
    Route::get('/voip',                [VoipConfiguracionController::class, 'index'])
        ->name('cobranza.voip.index');

    Route::get('/voip/configuracion',  [VoipConfiguracionController::class, 'show'])
        ->name('cobranza.voip.show');

    Route::post('/voip/configuracion', [VoipConfiguracionController::class, 'store'])
        ->name('cobranza.voip.store');

    Route::get('/voip/test',           [VoipConfiguracionController::class, 'testConexion'])
        ->name('cobranza.voip.test');
});

// Webhook AMI — sin auth, solo accesible desde 127.0.0.1
Route::middleware(['web'])->prefix('webhooks/cobranza')->group(function () {
    Route::post('/ami-event', [CobranzaWebhookController::class, 'amiEvent'])
        ->name('cobranza.webhook.ami');
});
