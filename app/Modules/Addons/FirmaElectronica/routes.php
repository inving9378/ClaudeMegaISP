<?php

use App\Modules\Addons\FirmaElectronica\Controllers\FirmaWebhookController;
use App\Modules\Addons\FirmaElectronica\Controllers\FirmaPublicaController;
use Illuminate\Support\Facades\Route;

/* Webhook público autenticado por firma HMAC (CSRF exento en VerifyCsrfToken). Item #565. */
Route::middleware(['web'])->post('/webhooks/firma-electronica', FirmaWebhookController::class)
    ->name('firma.webhook');

/* Pantalla pública del firmante (item #969): el token del enlace es la única credencial; sin sesión. */
Route::middleware(['web', 'throttle:30,1'])->prefix('firma')->group(function () {
    Route::get('/{token}', [FirmaPublicaController::class, 'mostrar'])->name('firma.publica');
    Route::get('/{token}/documento', [FirmaPublicaController::class, 'documento'])->name('firma.publica.documento');
    Route::post('/{token}/otp', [FirmaPublicaController::class, 'enviarOtp'])->middleware('throttle:5,1')->name('firma.publica.otp');
    Route::post('/{token}/firmar', [FirmaPublicaController::class, 'firmar'])->middleware('throttle:10,1')->name('firma.publica.firmar');
});
