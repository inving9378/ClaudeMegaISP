<?php

use App\Modules\Addons\PortalCliente\Controllers\AuthController;
use App\Modules\Addons\PortalCliente\Controllers\DashboardController;
use App\Modules\Addons\PortalCliente\Controllers\FacturasController;
use App\Modules\Addons\PortalCliente\Controllers\PagosController;
use App\Modules\Addons\PortalCliente\Controllers\PlanController;
use App\Modules\Addons\PortalCliente\Controllers\PortalPagoController;
use App\Modules\Addons\PortalCliente\Controllers\TicketsController;
use App\Modules\Addons\PortalCliente\Controllers\PerfilController;
use App\Modules\Addons\PortalCliente\Controllers\ConsumoController;
use App\Modules\Addons\PortalCliente\Controllers\MarketplaceController;
use Illuminate\Support\Facades\Route;

// ── Portal Cliente — rutas bajo /portal ────────────────────────────────────

Route::prefix('portal')->name('portal.')->middleware(['web'])->group(function () {

    // ── Rutas públicas (sin auth cliente) ─────────────────────────────────
    Route::get('/login',      [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login',     [AuthController::class, 'login'])->name('login.post');
    Route::get('/registro',   [AuthController::class, 'showRegistro'])->name('registro');
    Route::post('/registro',  [AuthController::class, 'registro'])->name('registro.post');
    Route::get('/recuperar',  [AuthController::class, 'showRecuperar'])->name('recuperar');
    Route::post('/recuperar', [AuthController::class, 'recuperar'])->name('recuperar.post');

    // ── Rutas protegidas (guard cliente) ──────────────────────────────────
    Route::middleware(['auth.portal'])->group(function () {

        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

        // Dashboard
        Route::get('/',          [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.alt');

        // Mi Plan / Servicio
        Route::get('/mi-plan', [PlanController::class, 'index'])->name('plan');

        // Facturas
        Route::get('/facturas',              [FacturasController::class, 'index'])->name('facturas');
        Route::get('/facturas/{id}',         [FacturasController::class, 'show'])->name('facturas.show');
        Route::post('/facturas/{id}/pagar',  [PortalPagoController::class, 'cobrar'])->name('facturas.pagar');

        // Pagos + CLABE
        Route::get('/pagos', [PagosController::class, 'index'])->name('pagos');

        // Consumo
        Route::get('/consumo', [ConsumoController::class, 'index'])->name('consumo');

        // Tickets
        Route::get('/tickets',              [TicketsController::class, 'index'])->name('tickets');
        Route::get('/tickets/{id}',         [TicketsController::class, 'show'])->name('tickets.show');
        Route::post('/tickets',             [TicketsController::class, 'store'])->name('tickets.store');
        Route::post('/tickets/{id}/reply',  [TicketsController::class, 'responder'])->name('tickets.reply');

        // Perfil
        Route::get('/perfil',                    [PerfilController::class, 'index'])->name('perfil');
        Route::post('/perfil/cambiar-password',  [PerfilController::class, 'cambiarPassword'])->name('perfil.password');
        Route::post('/perfil/actualizar-datos',  [PerfilController::class, 'actualizarDatos'])->name('perfil.actualizar');

        // Servicios / Marketplace
        Route::get('/servicios',                      [MarketplaceController::class, 'index'])->name('marketplace');
        Route::post('/servicios/megafamilia/activar', [MarketplaceController::class, 'activarMegafamilia'])->name('marketplace.megafamilia.activar');
        Route::post('/servicios/megafamilia/desactivar', [MarketplaceController::class, 'desactivarMegafamilia'])->name('marketplace.megafamilia.desactivar');
        Route::post('/servicios/interes',             [MarketplaceController::class, 'registrarInteres'])->name('marketplace.interes');
    });
});
