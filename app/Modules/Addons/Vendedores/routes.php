<?php

use App\Modules\Addons\Vendedores\Controllers\Sellers\BoxController;
use App\Modules\Addons\Vendedores\Controllers\Sellers\ExtraIncomeController;
use App\Modules\Addons\Vendedores\Controllers\Sellers\InstallationController;
use App\Modules\Addons\Vendedores\Controllers\Sellers\ObservationsController;
use App\Modules\Addons\Vendedores\Controllers\Sellers\SellerController as SellersSellerController;
use App\Modules\Addons\Vendedores\Controllers\Sellers\SuppliersExpensesController;
use App\Modules\Addons\Vendedores\Controllers\Vendors\Billing\PaymentClientController;
use App\Modules\Addons\Vendedores\Controllers\Vendors\Billing\PaymentSellerController;
use App\Modules\Addons\Vendedores\Controllers\Vendors\Billing\SellerTransactionController;
use App\Modules\Addons\Vendedores\Controllers\Vendors\Prospects\ProspectController;
use App\Modules\Addons\Vendedores\Controllers\Vendors\Sales\SaleController;
use App\Modules\Addons\Vendedores\Controllers\Vendors\SellerController as VendorsSellerController;
use App\Modules\Addons\Vendedores\Controllers\Vendors\VendorController;
use Illuminate\Support\Facades\Route;

/*
 * Rutas del módulo addon-vendedores.
 *
 * Agrupa dos sub-dominios aplanados (decisión arquitectura 2026-05-20):
 *  - Sellers (legacy `prefix=sellers`): vendedores, cortes diarios, cajas,
 *    ingresos extras, instalaciones, gastos a proveedores, observaciones.
 *  - Vendors (legacy `prefix=vendedores`): vendor general, ventas, prospectos,
 *    facturación, comisiones. Pendiente de migrar.
 *
 * `web` necesario porque loadRoutesFrom no aplica el grupo automáticamente.
 * `check_route_permission` replica el gating legacy.
 */

// ---------------------------------------------------------------
// Sellers — migrado de routes/web.php (namespace Sellers, prefix `sellers/*`)
// ---------------------------------------------------------------
Route::middleware(['web', 'auth', 'check_route_permission'])->prefix('sellers')->group(function () {
    Route::prefix('seller')->group(function () {
        Route::get('/', [SellersSellerController::class, 'index']);
        Route::post('/add', [SellersSellerController::class, 'store']);
        Route::get('/editar/{id}', [SellersSellerController::class, 'edit']);
        Route::post('/update/{id}', [SellersSellerController::class, 'update']);
        Route::post('/destroy/{id}', [SellersSellerController::class, 'destroy']);
        Route::post('/table', [SellersSellerController::class, 'table']);
        Route::post('/get-prospects/{id}', [SellersSellerController::class, 'table']);
    });

    Route::prefix('cuts')->group(function () {
        Route::resource('/extras-incomes', ExtraIncomeController::class)->except('index');
        Route::post('/extras-incomes-list/{id}', [ExtraIncomeController::class, 'index']);
        Route::resource('/installations', InstallationController::class)->except('index');
        Route::post('/installations-list/{id}', [InstallationController::class, 'index']);
        Route::resource('/suppliers-expenses', SuppliersExpensesController::class)->except('index');
        Route::post('/suppliers-expenses-list/{id}', [SuppliersExpensesController::class, 'index']);
        Route::resource('/observations', ObservationsController::class)->except('index');
        Route::post('/observations-list/{id}', [ObservationsController::class, 'index']);
        Route::get('/get-user-current-box/{id}', [BoxController::class, 'getCurrentBox']);
        Route::get('/box/{id}', [BoxController::class, 'findBox']);
        Route::get('/box-pdf/{id}', [BoxController::class, 'pdf']);
        Route::get('/get-received-payments-by-box/{id}', [BoxController::class, 'getReceivedPaymentsByBox']);
        Route::post('/close-user-current-box/{id}', [BoxController::class, 'close']);
        Route::get('/technicals', [BoxController::class, 'technicals']);
        Route::post('/{id}', [BoxController::class, 'cuts']);
    });
});

// ---------------------------------------------------------------
// Vendors (vendedores generales) — migrado de routes/web.php
// prefix `vendedores/*` con sub-grupos dashboard/prospectos/ventas/payments/etc.
// ---------------------------------------------------------------
Route::middleware(['web', 'auth', 'check_route_permission'])->prefix('vendedores')->group(function () {
    Route::get('/', [VendorsSellerController::class, 'showView'])->name('vendedores.showView');
    Route::get('/seguimiento-me/', [VendorsSellerController::class, 'showPanel'])->name('vendedores.showPanel');
    Route::get('/data', [VendorsSellerController::class, 'index'])->name('vendedores.index');
    Route::get('/{id}/seguimiento-vendedor/{seller_id}', [VendorsSellerController::class, 'edit'])->name('vendedores.seguimiento');
    Route::get('/{id}/getDataById', [VendorsSellerController::class, 'getDataById'])->name('vendedores.getDataById');
    Route::get('/get-status-sellers', [VendorsSellerController::class, 'getStatusSeller'])->name('vendedores.getStatusSeller');
    Route::get('/get-type-sellers', [VendorsSellerController::class, 'getTypesSeller'])->name('vendedores.getTypesSeller');
    Route::post('/{id}/update', [VendorsSellerController::class, 'update'])->name('vendedores.update');
    Route::get('/{id}/pdf', [VendorsSellerController::class, 'pdf'])->name('vendedores.pdf');

    // Dashboard
    Route::prefix('dashboard')->group(function () {
        Route::get('/', [VendorController::class, 'index'])->name('dashboard');
    });

    // Prospectos
    Route::prefix('prospectos')->group(function () {
        Route::get('/', [ProspectController::class, 'index'])->name('prospectos.index');
        Route::get('{id}/getById', [ProspectController::class, 'getById'])->name('prospectos.getById');
        Route::get('/statusProspects/{startDate}/{endDate}', [ProspectController::class, 'statusProspects'])->name('prospectos.statusProspects');
    });

    // Ventas
    Route::prefix('ventas')->group(function () {
        Route::get('/', [SaleController::class, 'index'])->name('ventas.index');
        Route::get('{id}/salesBySeller', [SaleController::class, 'salesBySeller'])->name('ventas.salesBySeller');
        Route::post('/sales-by-seller/{id}', [SaleController::class, 'getSalesBySeller']);
        Route::get('/salesByMedium/{startDate}/{endDate}', [SaleController::class, 'salesByMedium'])->name('ventas.salesByMedium');
        Route::get('/salesByMonth', [SaleController::class, 'salesByMonth'])->name('ventas.salesByMonth');
        Route::get('/salesAndProspectsByDateRange/{startDate}/{endDate}', [SaleController::class, 'salesAndProspectsByDateRange'])->name('ventas.salesAndProspectsByDateRange');
        Route::get('/rankingSales/{startDate}/{endDate}', [SaleController::class, 'rankingSales'])->name('ventas.rankingSales');

        Route::get('/total-prospects', [SaleController::class, 'getTotalProspects'])->name('ventas.getTotalProspects');
        Route::get('/total-sales', [SaleController::class, 'getTotalSales'])->name('ventas.getTotalSales');
        Route::get('/total-lost-sales', [SaleController::class, 'getLostSales'])->name('ventas.getLostSales');
    });

    // Pagos de los clientes
    Route::prefix('payments')->group(function () {
        Route::get('/', [PaymentClientController::class, 'index'])->name('index');
        Route::get('{id}/getListPayments', [PaymentClientController::class, 'getListPaymentsOfCustomersBySeller'])->name('getListPayments');
        Route::get('{id}/getPayments', [PaymentClientController::class, 'getPaymentsOfCustomersBySeller'])->name('getPayments');
        Route::get('{id}/getDataSeller', [PaymentClientController::class, 'getDataSeller'])->name('getDataSeller');
        Route::post('{id}/getRuleDataSeller', [PaymentClientController::class, 'getRuleDataSeller'])->name('getRuleDataSeller');
        Route::post('get-periods-from-seller/{id}', [PaymentClientController::class, 'getPeriodsFromSeller'])->name('getPeriodsFromSeller');
        Route::post('{id}/getMontlyCommissionsBySeller', [PaymentClientController::class, 'getMontlyCommissionsBySeller'])->name('getMontlyCommissionsBySeller');
    });

    // Pagos de los vendedores
    Route::prefix('payments-sellers')->group(function () {
        Route::get('{id}/get-all-payments-of-seller', [PaymentSellerController::class, 'getPaymentsBySellerId']);
        Route::get('{id_seller}/{id_payment}/get-ticket-of-seller', [PaymentSellerController::class, 'getTicket']);
        Route::get('/{id_seller}/{id_payment}/download-receipt-pdf', [PaymentSellerController::class, 'downloadReceipt']);
        Route::get('/{id}/edit-payment', [PaymentSellerController::class, 'getDataToEditPayment']);
        Route::get('/{id}/edit', [PaymentSellerController::class, 'edit']);
        Route::post('/create', [PaymentSellerController::class, 'registerPayment']);
        Route::post('/{id}/update', [PaymentSellerController::class, 'updatePayment']);
        Route::delete('/{id}/destroy', [PaymentSellerController::class, 'deletePayment']);
        Route::post('/store', [PaymentSellerController::class, 'store']);
        Route::post('/details', [PaymentSellerController::class, 'details']);
        Route::post('/details-from-payment-type', [PaymentSellerController::class, 'detailsFromPaymentType']);
        Route::post('/details-from-discount-type', [PaymentSellerController::class, 'detailsFromDiscountType']);
        Route::get('/payment-receipt/{id}', [PaymentSellerController::class, 'paymentReceiptPDF']);
        Route::get('/payment-receipt-by-type/{id}', [PaymentSellerController::class, 'paymentReceiptByTypePDF']);
        Route::get('/discount-receipt/{id}', [PaymentSellerController::class, 'discountReceiptPDF']);
        Route::post('/statement-account/{id}', [PaymentSellerController::class, 'statementAccount']);
        Route::post('/incomes-account/{id}', [PaymentSellerController::class, 'incomesAccount']);
        Route::post('/expenses-account/{id}', [PaymentSellerController::class, 'expensesAccount']);
        Route::post('/debt-account/{id}', [PaymentSellerController::class, 'debtAccount']);
        Route::post('/discount-account/{id}', [PaymentSellerController::class, 'discountAccount']);
        Route::post('/payments-by-seller/{id}', [PaymentSellerController::class, 'paymentsBySeller']);
        Route::post('/payment-signature/{id}', [PaymentSellerController::class, 'paymentSignature']);
        Route::post('/pending-payments-by-seller/{id}', [PaymentSellerController::class, 'pendingPaymentsBySeller']);
        Route::post('/discounts-by-seller/{id}', [PaymentSellerController::class, 'discountsBySeller']);
        Route::get('/pending-discounts/{id}', [PaymentSellerController::class, 'pendingDiscountsBySeller']);
        Route::post('/collect-debt', [PaymentSellerController::class, 'collectDebt']);
    });

    // Transacciones
    Route::prefix('transacciones')->group(function () {
        Route::get('{id_seller}/{start_date}/{end_date}/{method}/get-transactions-by-seller', [SellerTransactionController::class, 'getTransactionsList']);
    });
});
