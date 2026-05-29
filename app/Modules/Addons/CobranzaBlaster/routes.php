<?php

use App\Modules\Addons\CobranzaBlaster\Controllers\CobranzaCampanaController;
use App\Modules\Addons\CobranzaBlaster\Controllers\CobranzaWebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('cobranza-blaster')->group(function () {

    Route::get('/', [CobranzaCampanaController::class, 'index'])
        ->name('cobranza.index');

    Route::get('/campanas', [CobranzaCampanaController::class, 'index'])
        ->name('cobranza.campanas.index');

    Route::post('/campanas', [CobranzaCampanaController::class, 'store'])
        ->name('cobranza.campanas.store');

    Route::get('/campanas/{campana}', [CobranzaCampanaController::class, 'show'])
        ->name('cobranza.campanas.show');

    Route::put('/campanas/{campana}', [CobranzaCampanaController::class, 'update'])
        ->name('cobranza.campanas.update');

    Route::post('/campanas/{campana}/activar', [CobranzaCampanaController::class, 'activar'])
        ->name('cobranza.campanas.activar');

    Route::post('/campanas/{campana}/pausar', [CobranzaCampanaController::class, 'pausar'])
        ->name('cobranza.campanas.pausar');

    Route::post('/campanas/{campana}/completar', [CobranzaCampanaController::class, 'completar'])
        ->name('cobranza.campanas.completar');

    Route::get('/campanas/{campana}/estadisticas', [CobranzaCampanaController::class, 'estadisticas'])
        ->name('cobranza.campanas.estadisticas');

    Route::get('/campanas/{campana}/llamadas', [CobranzaCampanaController::class, 'llamadas'])
        ->name('cobranza.campanas.llamadas');

    Route::post('/campanas/{campana}/excluir-llamada/{llamada}', [CobranzaCampanaController::class, 'excluirLlamada'])
        ->name('cobranza.campanas.excluir-llamada');
});

// Webhook AMI — sin auth, solo accesible desde 127.0.0.1
Route::middleware(['web'])->prefix('webhooks/cobranza')->group(function () {
    Route::post('/ami-event', [CobranzaWebhookController::class, 'amiEvent'])
        ->name('cobranza.webhook.ami');
});
