<?php

use App\Modules\Addons\PortalCliente\Controllers\AuthController;
use App\Modules\Addons\PortalCliente\Controllers\OpenpayWebhookController;
use App\Modules\Addons\PortalCliente\Controllers\DashboardController;
use App\Modules\Addons\PortalCliente\Controllers\FacturasController;
use App\Modules\Addons\PortalCliente\Controllers\PagosController;
use App\Modules\Addons\PortalCliente\Controllers\PlanController;
use App\Modules\Addons\PortalCliente\Controllers\PortalPagoController;
use App\Modules\Addons\PortalCliente\Controllers\TicketsController;
use App\Modules\Addons\PortalCliente\Controllers\PerfilController;
use App\Modules\Addons\PortalCliente\Controllers\ConsumoController;
use App\Modules\Addons\PortalCliente\Controllers\MarketplaceController;
use App\Modules\Addons\PortalCliente\Controllers\EmbajadoresController;
use App\Modules\Addons\PortalCliente\Controllers\FlotasController;
use App\Modules\Addons\PortalCliente\Controllers\MegaFamiliaController;
use App\Modules\Addons\PortalCliente\Controllers\MegaFamiliaPerfilesController;
use App\Modules\Addons\PortalCliente\Controllers\MegaFamiliaDispositivosController;
use App\Modules\Addons\PortalCliente\Controllers\MegaFamiliaBloqueosController;
use App\Modules\Addons\PortalCliente\Controllers\MegaFamiliaHorariosController;
use App\Modules\Addons\PortalCliente\Controllers\MegaFamiliaGeofencesController;
use App\Modules\Addons\PortalCliente\Controllers\MegaFamiliaGamificacionController;
use Illuminate\Support\Facades\Route;

// ── Portal Cliente — rutas bajo /portal ────────────────────────────────────

Route::prefix('portal')->name('portal.')->middleware(['web'])->group(function () {

    // ── Webhook OpenPay (FUERA del guard cliente — lo llama OpenPay, no el cliente) ──
    // Autenticación: Basic Auth donde password = OPENPAY_PRIVATE_KEY (validado en el controller).
    // Activar URL en dashboard OpenPay cuando el subdominio/SSL esté publicado.
    Route::post('/openpay/webhook', [OpenpayWebhookController::class, 'handle'])
        ->name('openpay.webhook')
        ->withoutMiddleware(['auth', 'auth.portal']);

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
        // Edición de datos de contacto retirada: solo el admin modifica desde la ficha.

        // Servicios / Marketplace
        Route::get('/servicios',                      [MarketplaceController::class, 'index'])->name('marketplace');
        Route::post('/servicios/megafamilia/activar', [MarketplaceController::class, 'activarMegafamilia'])->name('marketplace.megafamilia.activar');
        Route::post('/servicios/megafamilia/desactivar', [MarketplaceController::class, 'desactivarMegafamilia'])->name('marketplace.megafamilia.desactivar');
        Route::post('/servicios/interes',             [MarketplaceController::class, 'registrarInteres'])->name('marketplace.interes');

        // Embajadores Meganet — panel del cliente (solo lectura, scopeado ->forClient)
        Route::get('/embajadores', [EmbajadoresController::class, 'index'])->name('embajadores');

        // Mi Flota — panel del cliente (solo lectura, scopeado ->forClient)
        Route::get('/flotas', [FlotasController::class, 'index'])->name('flotas');

        // MegaFamilia — panel del cliente (solo lectura, scopeado por client_isp_id)
        Route::get('/megafamilia', [MegaFamiliaController::class, 'index'])->name('megafamilia');

        // MegaFamilia — escrituras (scopeadas por forClient + ownership en controller)
        // G1: Perfiles de hijos
        Route::post('/megafamilia/perfiles',            [MegaFamiliaPerfilesController::class, 'store'])->name('megafamilia.perfiles.store');
        Route::get('/megafamilia/perfiles/{id}/edit',   [MegaFamiliaPerfilesController::class, 'edit'])->name('megafamilia.perfiles.edit');
        Route::put('/megafamilia/perfiles/{id}',        [MegaFamiliaPerfilesController::class, 'update'])->name('megafamilia.perfiles.update');
        Route::delete('/megafamilia/perfiles/{id}',     [MegaFamiliaPerfilesController::class, 'destroy'])->name('megafamilia.perfiles.destroy');

        // G2: Dispositivos
        Route::post('/megafamilia/dispositivos',          [MegaFamiliaDispositivosController::class, 'store'])->name('megafamilia.dispositivos.store');
        Route::get('/megafamilia/dispositivos/{id}/edit', [MegaFamiliaDispositivosController::class, 'edit'])->name('megafamilia.dispositivos.edit');
        Route::put('/megafamilia/dispositivos/{id}',      [MegaFamiliaDispositivosController::class, 'update'])->name('megafamilia.dispositivos.update');
        Route::delete('/megafamilia/dispositivos/{id}',   [MegaFamiliaDispositivosController::class, 'destroy'])->name('megafamilia.dispositivos.destroy');

        // G3: Bloqueos (app + web) por perfil
        Route::post('/megafamilia/perfiles/{profile}/app-blocks',        [MegaFamiliaBloqueosController::class, 'storeApp'])->name('megafamilia.appblocks.store');
        Route::delete('/megafamilia/perfiles/{profile}/app-blocks/{id}', [MegaFamiliaBloqueosController::class, 'destroyApp'])->name('megafamilia.appblocks.destroy');
        Route::post('/megafamilia/perfiles/{profile}/web-blocks',        [MegaFamiliaBloqueosController::class, 'storeWeb'])->name('megafamilia.webblocks.store');
        Route::delete('/megafamilia/perfiles/{profile}/web-blocks/{id}', [MegaFamiliaBloqueosController::class, 'destroyWeb'])->name('megafamilia.webblocks.destroy');

        // G4: Horarios de internet por perfil
        Route::post('/megafamilia/perfiles/{profile}/horarios',             [MegaFamiliaHorariosController::class, 'store'])->name('megafamilia.horarios.store');
        Route::get('/megafamilia/perfiles/{profile}/horarios/{id}/edit',    [MegaFamiliaHorariosController::class, 'edit'])->name('megafamilia.horarios.edit');
        Route::put('/megafamilia/perfiles/{profile}/horarios/{id}',         [MegaFamiliaHorariosController::class, 'update'])->name('megafamilia.horarios.update');
        Route::delete('/megafamilia/perfiles/{profile}/horarios/{id}',      [MegaFamiliaHorariosController::class, 'destroy'])->name('megafamilia.horarios.destroy');

        // G5: Geocercas familiares (atadas a un perfil; ownership por perfil)
        Route::post('/megafamilia/geocercas',           [MegaFamiliaGeofencesController::class, 'store'])->name('megafamilia.geocercas.store');
        Route::get('/megafamilia/geocercas/{id}/edit',  [MegaFamiliaGeofencesController::class, 'edit'])->name('megafamilia.geocercas.edit');
        Route::put('/megafamilia/geocercas/{id}',       [MegaFamiliaGeofencesController::class, 'update'])->name('megafamilia.geocercas.update');
        Route::delete('/megafamilia/geocercas/{id}',    [MegaFamiliaGeofencesController::class, 'destroy'])->name('megafamilia.geocercas.destroy');

        // G6: Tareas y recompensas (gamificación)
        Route::post('/megafamilia/perfiles/{profile}/tareas',            [MegaFamiliaGamificacionController::class, 'storeTarea'])->name('megafamilia.tareas.store');
        Route::delete('/megafamilia/perfiles/{profile}/tareas/{id}',     [MegaFamiliaGamificacionController::class, 'destroyTarea'])->name('megafamilia.tareas.destroy');
        Route::post('/megafamilia/perfiles/{profile}/recompensas',       [MegaFamiliaGamificacionController::class, 'storeRecompensa'])->name('megafamilia.recompensas.store');
        Route::delete('/megafamilia/perfiles/{profile}/recompensas/{id}',[MegaFamiliaGamificacionController::class, 'destroyRecompensa'])->name('megafamilia.recompensas.destroy');
        Route::post('/megafamilia/tareas/{tarea}/completar',             [MegaFamiliaGamificacionController::class, 'completarTarea'])->name('megafamilia.tareas.completar');
    });
});
