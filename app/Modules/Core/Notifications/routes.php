<?php

use App\Modules\Core\Notifications\Controllers\PushTokenController;
use Illuminate\Support\Facades\Route;

/*
 * Rutas del módulo core-notifications (item #627).
 *
 * Infra transversal de push tokens FCM: registro/baja únicamente. Sin
 * grupo `web` (no hay UI ni sesión); `force_json` porque estas rutas no
 * pasan por el grupo `api` nativo de Laravel (loadRoutesFrom no lo aplica),
 * así que sin él una excepción de validación/auth devolvería un redirect
 * en vez de JSON. `auth:sanctum` = identidad del dispositivo móvil.
 */

Route::middleware(['force_json', 'auth:sanctum'])
    ->prefix('api/push-tokens')
    ->group(function () {
        Route::post('/', [PushTokenController::class, 'store']);
        Route::delete('/{token}', [PushTokenController::class, 'destroy']);
    });
