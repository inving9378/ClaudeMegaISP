<?php

use App\Modules\Addons\WhatsAppAgent\Controllers\WhatsAppFunctionController;
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
        Route::get('/funciones', [WhatsAppFunctionController::class,  'panel'])->name('funciones'); // Fase 3

        Route::prefix('api')->group(function () {
            // Conversaciones
            Route::get('/conversations',                    [WhatsAppPanelController::class, 'conversations']);
            Route::get('/conversations/{id}/messages',      [WhatsAppPanelController::class, 'messages'])->whereNumber('id');
            Route::post('/conversations/{id}/send',         [WhatsAppPanelController::class, 'send'])->whereNumber('id');
            Route::post('/conversations/{id}/mark-read',    [WhatsAppPanelController::class, 'markRead'])->whereNumber('id');
            Route::post('/conversations/{id}/close',        [WhatsAppPanelController::class, 'close'])->whereNumber('id');
            Route::post('/conversations/{id}/ia-assist',    [WhatsAppPanelController::class, 'iaAssist'])->whereNumber('id');
            Route::post('/conversations/{id}/schedule-installation', [WhatsAppPanelController::class, 'scheduleInstallation'])->whereNumber('id');

            // Helper: técnicos disponibles para el selector del modal
            Route::get('/technicians', [WhatsAppPanelController::class, 'technicians']);

            // Instancias
            Route::get('/instances/functions-catalog', [WhatsAppInstanceController::class, 'functionsCatalog']); // Fase 3 (antes de {id})
            Route::get('/instances',                [WhatsAppInstanceController::class, 'index']);
            Route::post('/instances',               [WhatsAppInstanceController::class, 'store']);
            Route::patch('/instances/{id}',         [WhatsAppInstanceController::class, 'update'])->whereNumber('id');
            Route::delete('/instances/{id}',        [WhatsAppInstanceController::class, 'destroy'])->whereNumber('id');
            Route::get('/instances/{id}/qr',        [WhatsAppInstanceController::class, 'getQr'])->whereNumber('id');
            Route::get('/instances/{id}/status',    [WhatsAppInstanceController::class, 'connectionStatus'])->whereNumber('id');
            Route::post('/instances/{id}/disconnect', [WhatsAppInstanceController::class, 'disconnect'])->whereNumber('id'); // logout real en Evolution

            // Funciones por línea (Fase 3) — gate whatsapp_manage_instances
            Route::post('/instances/{id}/functions',                        [WhatsAppInstanceController::class, 'assignFunction'])->whereNumber('id');
            Route::delete('/instances/{id}/functions/{functionId}',         [WhatsAppInstanceController::class, 'unassignFunction'])->whereNumber('id')->whereNumber('functionId');
            Route::post('/instances/{id}/functions/{functionId}/reassign',  [WhatsAppInstanceController::class, 'reassignFunction'])->whereNumber('id')->whereNumber('functionId');

            // Catálogo de funciones (Fase 3) — gate whatsapp_manage_functions
            Route::get('/functions',                  [WhatsAppFunctionController::class, 'index']);
            Route::post('/functions',                 [WhatsAppFunctionController::class, 'store']);
            Route::patch('/functions/{id}',           [WhatsAppFunctionController::class, 'update'])->whereNumber('id');
            Route::patch('/functions/{id}/exclusive', [WhatsAppFunctionController::class, 'toggleExclusive'])->whereNumber('id');
            Route::delete('/functions/{id}',          [WhatsAppFunctionController::class, 'destroy'])->whereNumber('id');

            // Envío programático
            Route::post('/send', [WhatsAppSendController::class, 'send']);
        });
    });
