<?php

use App\Modules\Addons\PortalPago\Controllers\PublicPagoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Portal de Pago — rutas públicas por token (sin auth)
|--------------------------------------------------------------------------
| 'web' aporta sesión + CSRF (NO es la sesión admin: no hay 'auth').
| 'throttle:10,1' limita a 10 solicitudes por minuto por IP.
*/
Route::prefix('f')->name('portal.pago.')
    ->middleware(['web', 'throttle:10,1'])
    ->group(function () {
        Route::get('/{token}', [PublicPagoController::class, 'show'])->name('show');
    });
