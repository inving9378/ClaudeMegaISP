<?php

use App\Modules\Addons\PortalPago\Controllers\Admin\ConciliacionController;
use App\Modules\Addons\PortalPago\Controllers\Admin\CuentasController;
use App\Modules\Addons\PortalPago\Controllers\Admin\DashboardController;
use App\Modules\Addons\PortalPago\Controllers\Admin\LinksController;
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
        Route::post('/{token}/reportar', [PublicPagoController::class, 'reportar'])->name('reportar');
        Route::get('/{token}/estado', [PublicPagoController::class, 'estado'])->name('estado');
    });

/*
|--------------------------------------------------------------------------
| Portal de Pago — backend admin (web + auth). Gate por permiso con can:.
|--------------------------------------------------------------------------
*/
Route::middleware(['web', 'auth'])->name('admin.pagos.')->group(function () {

    // Pantallas (Blade + Vue/Quasar)
    Route::get('/pagos', [DashboardController::class, 'index'])->middleware('can:pagos.view')->name('dashboard');
    Route::get('/pagos/conciliacion', [ConciliacionController::class, 'index'])->middleware('can:pagos.conciliar')->name('conciliacion');
    Route::get('/pagos/cuentas', [CuentasController::class, 'index'])->middleware('can:pagos.cuentas.manage')->name('cuentas');
    Route::get('/pagos/links', [LinksController::class, 'index'])->middleware('can:pagos.links.manage')->name('links');

    // Descarga protegida de comprobante (nunca expone storage/app/private)
    Route::get('/pagos/comprobante/{report}', [ConciliacionController::class, 'comprobante'])->middleware('can:pagos.conciliar')->name('comprobante');

    // API JSON (axios)
    Route::prefix('api/pagos')->group(function () {
        Route::get('/kpis', [DashboardController::class, 'kpis'])->middleware('can:pagos.view');

        Route::middleware('can:pagos.conciliar')->group(function () {
            Route::get('/conciliacion', [ConciliacionController::class, 'list']);
            Route::post('/conciliacion/{report}/aprobar', [ConciliacionController::class, 'aprobar']);
            Route::post('/conciliacion/{report}/rechazar', [ConciliacionController::class, 'rechazar']);
        });

        Route::middleware('can:pagos.cuentas.manage')->group(function () {
            Route::get('/cuentas', [CuentasController::class, 'list']);
            Route::post('/cuentas', [CuentasController::class, 'store']);
            Route::put('/cuentas/{account}', [CuentasController::class, 'update']);
            Route::post('/cuentas/{account}/toggle', [CuentasController::class, 'toggle']);
        });

        Route::middleware('can:pagos.links.manage')->group(function () {
            Route::get('/links', [LinksController::class, 'list']);
            Route::get('/clientes/buscar', [LinksController::class, 'buscarClientes']);
            Route::get('/clientes/{clientId}/facturas', [LinksController::class, 'facturasCliente']);
            Route::post('/links', [LinksController::class, 'generar']);
        });
    });
});
