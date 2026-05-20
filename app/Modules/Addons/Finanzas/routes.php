<?php

use App\Modules\Addons\Finanzas\Controllers\GeneralAccounting\Category\GeneralAccountingCategoryController;
use App\Modules\Addons\Finanzas\Controllers\GeneralAccounting\Expense\GeneralAccountingExpenseController;
use App\Modules\Addons\Finanzas\Controllers\GeneralAccounting\GeneralAccountingController;
use App\Modules\Addons\Finanzas\Controllers\GeneralAccounting\Income\GeneralAccountingIncomeController;
use App\Modules\Addons\Finanzas\Controllers\GeneralAccounting\Operation\GeneralAccountingOperationController;
use App\Modules\Addons\Finanzas\Controllers\Invoice\FinanceInvoiceController;
use App\Modules\Addons\Finanzas\Controllers\Invoice\InvoiceController;
use App\Modules\Addons\Finanzas\Controllers\Payment\FinancePaymentController;
use App\Modules\Addons\Finanzas\Controllers\Transaction\FinanceTransactionController;
use Illuminate\Support\Facades\Route;

/*
 * Rutas del módulo addon-finanzas.
 *
 * Sub-dominios preservados con sub-namespaces (3 niveles):
 *  - GeneralAccounting/{Category, Expense, Income, Operation}/
 *  - Invoice/                (InvoiceController + FinanceInvoiceController)
 *  - Payment/
 *  - Transaction/
 *
 * URL prefix `finanzas/*` preservado para compat con frontend.
 *
 * `web` necesario porque loadRoutesFrom no aplica el grupo automáticamente.
 * `check_route_permission` replica el gating legacy.
 */

Route::middleware(['web', 'auth', 'check_route_permission'])->prefix('finanzas')->group(function () {

    Route::prefix('transacciones')->group(function () {
        Route::get('/', [FinanceTransactionController::class, 'index']);
        Route::post('/table', [FinanceTransactionController::class, 'table']);
    });

    Route::prefix('facturas')->group(function () {
        Route::get('/', [FinanceInvoiceController::class, 'index']);
        Route::post('/table', [FinanceInvoiceController::class, 'table']);
    });

    Route::prefix('pagos')->group(function () {
        Route::get('/', [FinancePaymentController::class, 'index']);
        Route::post('/table', [FinancePaymentController::class, 'table']);
    });

    Route::prefix('invoices')->group(function () {
        Route::get('/', [InvoiceController::class, 'index']);
        Route::post('/table', [InvoiceController::class, 'table']);
        Route::post('/create-for-client/{id}', [InvoiceController::class, 'createForClient']);

        Route::post('/send/{id}', [InvoiceController::class, 'sendInvoice']);
        Route::get('/print/{id}', [InvoiceController::class, 'printInvoice']);
        Route::post('/mark-as-paid/{id}', [InvoiceController::class, 'markAsPaid']);
        Route::post('/edit-period/{id}', [InvoiceController::class, 'editPeriod']);

        Route::post('/get-pending-by-client/{id}', [InvoiceController::class, 'getPendingByClient']);
        Route::post('/destroy/{id}', [InvoiceController::class, 'destroy']);
        Route::get('/get-available-periods-by-client/{id}', [InvoiceController::class, 'getAvailablePeriodsByClient']);
    });

    Route::prefix('general-accounting')->group(function () {
        Route::get('/', [GeneralAccountingController::class, 'index']);
        Route::get('/get-data', [GeneralAccountingController::class, 'getData']);
        Route::get('/get-bar-data', [GeneralAccountingController::class, 'getBarData']);
        Route::get('/get-donut-data', [GeneralAccountingController::class, 'getDonutData']);

        Route::prefix('income')->group(function () {
            Route::post('/table', [GeneralAccountingIncomeController::class, 'table']);
            Route::post('/add', [GeneralAccountingIncomeController::class, 'store']);
        });

        Route::prefix('expense')->group(function () {
            Route::post('/table', [GeneralAccountingExpenseController::class, 'table']);
            Route::post('/add', [GeneralAccountingExpenseController::class, 'store']);
        });

        Route::prefix('operation')->group(function () {
            Route::post('/add', [GeneralAccountingOperationController::class, 'store']);
            Route::post('/update/{id}', [GeneralAccountingOperationController::class, 'update']);
        });

        Route::prefix('category')->group(function () {
            Route::post('/add', [GeneralAccountingCategoryController::class, 'store']);
        });
    });
});
