<?php

use App\Modules\Addons\WhatsAppAgent\Controllers\WhatsAppInstanceController;
use App\Modules\Addons\WhatsAppAgent\Controllers\WhatsAppPanelController;
use App\Modules\Addons\WhatsAppAgent\Controllers\WhatsAppSendController;
use App\Modules\Addons\WhatsAppAgent\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

/*
 * Rutas del módulo addon-whatsapp-agent.
 *
 * Webhook entrante (público) → /whatsapp/webhook/{slug}
 *   Listado en PUBLIC_ROUTES de CheckRoutePermission. Auth por X-Webhook-Secret.
 *
 * Panel staff (auth + check_route_permission) → /whatsapp/*
 *   Vista index + endpoints API JSON. Vendedores se filtran por seller_id
 *   en el controller (no a nivel de ruta).
 */

// ── PÚBLICA: webhook Evolution API ───────────────────────────────────────────
Route::middleware(['web'])->group(function () {
    Route::post('/whatsapp/webhook/{slug}', [WhatsAppWebhookController::class, 'handle'])
        ->name('whatsapp.webhook');
});

// ── STAFF: panel + API ───────────────────────────────────────────────────────
Route::middleware(['web', 'auth', 'check_route_permission'])
    ->prefix('whatsapp')
    ->name('whatsapp.')
    ->group(function () {

        // Vistas
        Route::get('/',          [WhatsAppPanelController::class,    'index'])->name('panel');
        Route::get('/instances', [WhatsAppInstanceController::class, 'panel'])->name('instances');

        Route::prefix('api')->group(function () {
            // Conversaciones
            Route::get('/conversations',                    [WhatsAppPanelController::class, 'conversations']);
            Route::get('/conversations/{id}/messages',      [WhatsAppPanelController::class, 'messages'])->whereNumber('id');
            Route::post('/conversations/{id}/send',         [WhatsAppPanelController::class, 'send'])->whereNumber('id');
            Route::post('/conversations/{id}/mark-read',    [WhatsAppPanelController::class, 'markRead'])->whereNumber('id');
            Route::post('/conversations/{id}/close',        [WhatsAppPanelController::class, 'close'])->whereNumber('id');
            Route::post('/conversations/{id}/ia-assist',    [WhatsAppPanelController::class, 'iaAssist'])->whereNumber('id');

            // Instancias
            Route::get('/instances',                [WhatsAppInstanceController::class, 'index']);
            Route::post('/instances',               [WhatsAppInstanceController::class, 'store']);
            Route::patch('/instances/{id}',         [WhatsAppInstanceController::class, 'update'])->whereNumber('id');
            Route::delete('/instances/{id}',        [WhatsAppInstanceController::class, 'destroy'])->whereNumber('id');
            Route::get('/instances/{id}/qr',        [WhatsAppInstanceController::class, 'getQr'])->whereNumber('id');
            Route::get('/instances/{id}/status',    [WhatsAppInstanceController::class, 'connectionStatus'])->whereNumber('id');

            // Envío programático
            Route::post('/send', [WhatsAppSendController::class, 'send']);
        });
    });
