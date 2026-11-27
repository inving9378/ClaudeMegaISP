<?php

use App\Modules\Addons\CobranzaBlaster\Controllers\CampanaController;
use App\Modules\Addons\CobranzaBlaster\Controllers\DashboardController;
use App\Modules\Addons\CobranzaBlaster\Controllers\VoipConfiguracionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('cobranza')->group(function () {

    // ── Dashboard: todas las llamadas de la central con su grabación (24-nov) ──
    Route::get('/dashboard',          [DashboardController::class, 'index'])
        ->name('cobranza.dashboard.index');
    Route::get('/dashboard/data',     [DashboardController::class, 'data'])
        ->name('cobranza.dashboard.data');
    Route::get('/dashboard/llamadas', [DashboardController::class, 'llamadas'])
        ->name('cobranza.dashboard.llamadas');

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

    // Voz de la campaña: opciones y escuchar la nota antes de crearla (24-nov)
    Route::get('/campanas/voz',         [CampanaController::class, 'voz'])
        ->name('cobranza.campanas.voz');

    Route::post('/campanas/escuchar',   [CampanaController::class, 'escuchar'])
        ->middleware('throttle:20,1')
        ->name('cobranza.campanas.escuchar');

    // A quién se le llama: estados, zona, caja y la lista para revisarla antes de llamar (27-nov)
    Route::get('/campanas/audiencia',   [CampanaController::class, 'audiencia'])
        ->name('cobranza.campanas.audiencia');

    Route::post('/campanas/{id}/activar', [CampanaController::class, 'activar'])
        ->name('cobranza.campanas.activar');

    Route::post('/campanas/{id}/pausar',  [CampanaController::class, 'pausar'])
        ->name('cobranza.campanas.pausar');

    // Reanudar una pausada (25-nov)
    Route::post('/campanas/{id}/reanudar', [CampanaController::class, 'reanudar'])
        ->name('cobranza.campanas.reanudar');

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
