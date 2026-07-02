<?php

use App\Modules\Addons\Payments\Controllers\ClabeAssignmentController;
use App\Modules\Addons\Payments\Controllers\ManualPaymentController;
use App\Modules\Addons\Payments\Controllers\MobilePaymentController;
use App\Modules\Addons\Payments\Controllers\PaymentProviderController;
use App\Modules\Addons\Payments\Controllers\ReconciliationController;
use App\Modules\Addons\Payments\Controllers\ReceiptController;
use App\Modules\Addons\Payments\Controllers\ReceiptExtractionTestController;
use App\Modules\Addons\Payments\Controllers\WhatsappReceiptReviewController;
use App\Modules\Addons\Payments\Controllers\ReconciliationSimulatorController;
use App\Modules\Addons\Payments\Controllers\ReconciliationQueueController;
use App\Modules\Addons\Payments\Controllers\SpeiWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Payments module routes
|--------------------------------------------------------------------------
*/

// ── PÚBLICA: webhook entrante de OpenPay ──────────────────────────────────
// SIN auth, SIN check_route_permission, SIN CSRF (exclusión está en
// app/Http/Middleware/VerifyCsrfToken.php + PUBLIC_ROUTES en
// CheckRoutePermission). Validación de firma vía HTTP Basic Auth en el
// controller contra payment_providers.config.webhook_secret.
//
// Envuelto en ['web'] porque BaseModuleServiceProvider::loadRoutesFrom() no
// aplica el middleware group 'web' por default (ver memoria
// feedback_module_routes_web_middleware).
Route::middleware(['web'])->group(function () {
    Route::post('/payments/spei/webhook', [SpeiWebhookController::class, 'handle'])
        ->name('payments.spei.webhook');
});

// ── ADMIN: endpoints CRUD ─────────────────────────────────────────────────
// Permisos mapeados en config/route_permission.php (entries 'payments_*').
Route::middleware(['web', 'auth', 'check_route_permission'])
    ->prefix('finanzas')
    ->name('finanzas.')
    ->group(function () {

        // Vista Blade del CRUD (renderiza <payment-methods></payment-methods>)
        Route::get('/metodos-pago', function () {
            return view('addon-payments::metodos-pago');
        })->name('metodos-pago');

        // Cola de conciliación (motor de cobro nativo) — reconciliation_tickets.
        // Tablero dedicado; cierra el 404 del banner del listado de clientes.
        Route::get('/conciliacion',                  [ReconciliationController::class, 'index'])->name('conciliacion');
        Route::get('/conciliacion/list',             [ReconciliationController::class, 'list'])->name('conciliacion.list');
        Route::post('/conciliacion/{id}/resolver',   [ReconciliationController::class, 'resolve'])->whereNumber('id')->name('conciliacion.resolve');
        Route::post('/conciliacion/{id}/descartar',  [ReconciliationController::class, 'dismiss'])->whereNumber('id')->name('conciliacion.dismiss');

        // Captura de pago reportado por mostrador (Paso 2) — aplica el pago
        // (PaymentApplicationService) y lo registra en reported_payments.
        Route::get('/captura-pago',                  [ManualPaymentController::class, 'create'])->name('captura-pago');
        Route::get('/captura-pago/buscar-cliente',   [ManualPaymentController::class, 'buscarCliente'])->name('captura-pago.buscar');
        Route::post('/captura-pago',                 [ManualPaymentController::class, 'store'])->name('captura-pago.store');
        Route::get('/captura-pago/{id}/comprobante', [ManualPaymentController::class, 'descargarComprobante'])->whereNumber('id')->name('captura-pago.comprobante');

        // Proveedores de pago (payment_providers)
        Route::get('/payment-providers',         [PaymentProviderController::class, 'index'])->name('payment-providers.index');
        Route::get('/payment-providers/{id}',    [PaymentProviderController::class, 'show'])->whereNumber('id');
        Route::post('/payment-providers',        [PaymentProviderController::class, 'store']);
        Route::put('/payment-providers/{id}',    [PaymentProviderController::class, 'update'])->whereNumber('id');
        Route::delete('/payment-providers/{id}', [PaymentProviderController::class, 'destroy'])->whereNumber('id');

        // CLABE virtual por cliente
        Route::get('/clients/{id}/clabe',         [ClabeAssignmentController::class, 'show'])->whereNumber('id');
        Route::post('/clients/{id}/assign-clabe', [ClabeAssignmentController::class, 'assign'])->whereNumber('id');

        // Comprobantes adjuntos a un pago
        Route::get('/payments/{id}/receipt',                        [ReceiptController::class, 'index'])->whereNumber('id');
        Route::get('/payments/{payment}/receipt/{receipt}/download', [ReceiptController::class, 'download'])
            ->whereNumber('payment')->whereNumber('receipt')
            ->name('payments.receipt.download');
    });

// ── PRUEBA IA (FASE PAGOS 3, pieza 2): extracción de comprobante ──────────
// Pantalla AISLADA de prueba (Irving): sube imagen → ve qué extrajo la IA.
// NO aplica pagos, NO busca cliente, NO WhatsApp. Gateada por ROL (temporal,
// solo para pruebas) vía el middleware Spatie 'role' registrado en el Kernel.
Route::middleware(['web', 'auth', 'role:super-administrator|DESARROLLADOR'])
    ->prefix('finanzas')
    ->name('finanzas.')
    ->group(function () {
        Route::get('/extraccion-comprobante',          [ReceiptExtractionTestController::class, 'index'])->name('extraccion-comprobante');
        Route::post('/extraccion-comprobante/procesar', [ReceiptExtractionTestController::class, 'procesar'])->name('extraccion-comprobante.procesar');

        // FASE 2 — Revisión de comprobantes recibidos por WhatsApp. SOLO leer +
        // "Extraer con IA" (manual). NO aplica pago, NO identifica, NO responde.
        Route::get('/whatsapp-comprobantes',                    [WhatsappReceiptReviewController::class, 'index'])->name('whatsapp-comprobantes');
        Route::get('/whatsapp-comprobantes/{message}/media',    [WhatsappReceiptReviewController::class, 'media'])->whereNumber('message')->name('whatsapp-comprobantes.media');
        Route::post('/whatsapp-comprobantes/{message}/extraer', [WhatsappReceiptReviewController::class, 'extract'])->whereNumber('message')->name('whatsapp-comprobantes.extraer');

        // FASE 3 (F3.4) — SIMULADOR de la conversación de identificación. NO envía
        // mensajes reales; corre el mismo FSM sobre sesiones is_simulation.
        Route::get('/conciliacion-sim',          [ReconciliationSimulatorController::class, 'index'])->name('conciliacion-sim');
        Route::post('/conciliacion-sim/iniciar', [ReconciliationSimulatorController::class, 'start'])->name('conciliacion-sim.iniciar');
        Route::post('/conciliacion-sim/responder',[ReconciliationSimulatorController::class, 'reply'])->name('conciliacion-sim.responder');
        Route::post('/conciliacion-sim/avanzar',  [ReconciliationSimulatorController::class, 'advance'])->name('conciliacion-sim.avanzar');
        Route::post('/conciliacion-sim/reiniciar',[ReconciliationSimulatorController::class, 'reset'])->name('conciliacion-sim.reiniciar');
    });

// FASE 6 — Cola de conciliación de Tere. Gateada por el permiso PROPIO
// conciliacion.manage (Tere/super-administrator + DESARROLLADOR).
Route::middleware(['web', 'auth', 'permission:conciliacion.manage'])
    ->prefix('finanzas')
    ->name('finanzas.')
    ->group(function () {
        Route::get('/conciliacion-cola',                       [ReconciliationQueueController::class, 'index'])->name('conciliacion-cola');
        Route::get('/conciliacion-cola/pendientes',            [ReconciliationQueueController::class, 'pendingCount'])->name('conciliacion-cola.pendientes');
        Route::get('/conciliacion-cola/list',                  [ReconciliationQueueController::class, 'list'])->name('conciliacion-cola.list');
        Route::get('/conciliacion-cola/clientes/buscar',       [ReconciliationQueueController::class, 'searchClients'])->name('conciliacion-cola.clientes');
        Route::get('/conciliacion-cola/{session}/detalle',     [ReconciliationQueueController::class, 'show'])->whereNumber('session')->name('conciliacion-cola.detalle');
        Route::get('/conciliacion-cola/{session}/media',       [ReconciliationQueueController::class, 'media'])->whereNumber('session')->name('conciliacion-cola.media');
        Route::post('/conciliacion-cola/{session}/confirmar',  [ReconciliationQueueController::class, 'confirm'])->whereNumber('session')->name('conciliacion-cola.confirmar');
        Route::post('/conciliacion-cola/{session}/rechazar',   [ReconciliationQueueController::class, 'reject'])->whereNumber('session')->name('conciliacion-cola.rechazar');
    });

// ── API MOBILE: endpoints consumidos por la app Flutter MegaFamilia ─────
// Mismo patrón que el módulo MegaFamilia (prefix api/megafamilia + sanctum +
// log_api_mobile). NO usa 'web' middleware → no CSRF, ideal para multipart
// uploads con token auth.
Route::prefix('api/megafamilia/payments')
    ->middleware(['log_api_mobile', 'auth:sanctum'])
    ->name('mobile.payments.')
    ->group(function () {
        Route::get('/clabe',           [MobilePaymentController::class, 'getClabe'])->name('clabe');
        Route::post('/notify-transfer', [MobilePaymentController::class, 'notifyTransfer'])->name('notify-transfer');
    });
