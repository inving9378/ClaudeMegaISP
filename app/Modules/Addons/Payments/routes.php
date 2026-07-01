<?php

use App\Modules\Addons\Payments\Controllers\ClabeAssignmentController;
use App\Modules\Addons\Payments\Controllers\MobilePaymentController;
use App\Modules\Addons\Payments\Controllers\PaymentProviderController;
use App\Modules\Addons\Payments\Controllers\ReconciliationController;
use App\Modules\Addons\Payments\Controllers\ReceiptController;
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
