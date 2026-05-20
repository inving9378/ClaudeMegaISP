<?php

use App\Modules\Addons\Vendedores\Controllers\Sellers\BoxController;
use App\Modules\Addons\Vendedores\Controllers\Sellers\ExtraIncomeController;
use App\Modules\Addons\Vendedores\Controllers\Sellers\InstallationController;
use App\Modules\Addons\Vendedores\Controllers\Sellers\ObservationsController;
use App\Modules\Addons\Vendedores\Controllers\Sellers\SellerController;
use App\Modules\Addons\Vendedores\Controllers\Sellers\SuppliersExpensesController;
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
        Route::get('/', [SellerController::class, 'index']);
        Route::post('/add', [SellerController::class, 'store']);
        Route::get('/editar/{id}', [SellerController::class, 'edit']);
        Route::post('/update/{id}', [SellerController::class, 'update']);
        Route::post('/destroy/{id}', [SellerController::class, 'destroy']);
        Route::post('/table', [SellerController::class, 'table']);
        Route::post('/get-prospects/{id}', [SellerController::class, 'table']);
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
