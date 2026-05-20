<?php

use App\Modules\Addons\Mensajes\Controllers\InboxController;
use App\Modules\Addons\Mensajes\Controllers\InvoiceEmailController;
use App\Modules\Addons\Mensajes\Controllers\ProformaInvoiceEmailController;
use App\Modules\Addons\Mensajes\Controllers\ReminderController;
use Illuminate\Support\Facades\Route;

/*
 * Rutas del módulo addon-mensajes.
 *
 * Sub-dominios aplanados a Mensajes/Controllers/ (decisión 2026-05-20):
 *  - InboxController, ReminderController, InvoiceEmailController,
 *    ProformaInvoiceEmailController, PaymentEmailController.
 *
 * PaymentEmailController existe en el módulo pero sus rutas estaban
 * comentadas en routes/web.php legacy — se preservan comentadas aquí.
 *
 * `web` necesario porque loadRoutesFrom no aplica el grupo automáticamente.
 * `check_route_permission` replica el gating legacy.
 */

Route::middleware(['web', 'auth', 'check_route_permission'])->prefix('message')->group(function () {

    Route::prefix('inbox')->group(function () {
        Route::get('/', [InboxController::class, 'index']);
        Route::post('/get-data-tabs', [InboxController::class, 'getDataTabs']);
    });

    Route::prefix('reminder')->group(function () {
        Route::post('/table', [ReminderController::class, 'table']);
        Route::post('/send_message', [ReminderController::class, 'sendMessage']);
    });

    // PaymentEmailController commented in legacy routes; preserved for parity.
    // Route::prefix('payment_email')->group(function () {
    //     Route::post('/table', [PaymentEmailController::class, 'table']);
    //     Route::post('/send_message', [PaymentEmailController::class, 'sendMessage']);
    // });

    Route::prefix('invoice_email')->group(function () {
        Route::post('/table', [InvoiceEmailController::class, 'table']);
        // Route::post('/send_message', [PaymentEmailController::class, 'sendMessage']);
    });

    Route::prefix('proforma_invoice_email')->group(function () {
        Route::post('/table', [ProformaInvoiceEmailController::class, 'table']);
        Route::post('/send_message', [ProformaInvoiceEmailController::class, 'sendMessage']);
    });
});
