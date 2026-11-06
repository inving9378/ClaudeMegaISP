<?php

use App\Modules\Addons\PortalCliente\Controllers\Admin\AparienciaController;
use App\Modules\Addons\PortalCliente\Controllers\AparienciaImagenController;
use App\Modules\Addons\PortalCliente\Controllers\AuthController;
use App\Modules\Addons\PortalCliente\Controllers\PasswordOtpController;
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
use App\Modules\Addons\PortalCliente\Controllers\FlotasGeocercasController;
use App\Modules\Addons\PortalCliente\Controllers\FlotasPanelController;
use App\Modules\Addons\PortalCliente\Controllers\FlotasReglasController;
use App\Modules\Addons\PortalCliente\Controllers\FlotasOperadoresController;
use App\Modules\Addons\PortalCliente\Controllers\FlotasAlertasController;
use App\Modules\Addons\PortalCliente\Controllers\FlotasContactosController;
use App\Modules\Addons\Flotas\Controllers\FleetVehicleController as PFleetVehicle;
use App\Modules\Addons\Flotas\Controllers\FleetMaintenanceController as PFleetMaintenance;
use App\Modules\Addons\Flotas\Controllers\FleetDocumentController as PFleetDocument;
use App\Modules\Addons\Flotas\Controllers\FleetFuelLogController as PFleetFuel;
use App\Modules\Addons\Flotas\Controllers\FleetPhotoController as PFleetPhoto;
use App\Modules\Addons\Flotas\Controllers\FleetGpsController as PFleetGps;
use App\Modules\Addons\Flotas\Controllers\FleetGeofenceController as PFleetGeofence;
use App\Modules\Addons\Flotas\Controllers\FleetNotificationController as PFleetNotification;
use App\Modules\Addons\PortalCliente\Controllers\MegaFamiliaController;
use App\Modules\Addons\PortalCliente\Controllers\MegaFamiliaPerfilesController;
use App\Modules\Addons\PortalCliente\Controllers\MegaFamiliaDispositivosController;
use App\Modules\Addons\PortalCliente\Controllers\MegaFamiliaBloqueosController;
use App\Modules\Addons\PortalCliente\Controllers\MegaFamiliaHorariosController;
use App\Modules\Addons\PortalCliente\Controllers\MegaFamiliaGeofencesController;
use App\Modules\Addons\PortalCliente\Controllers\MegaFamiliaGamificacionController;
use App\Modules\Addons\PortalCliente\Controllers\MegaFamiliaAsignacionTareasController;
use App\Modules\Addons\PortalCliente\Controllers\MegaFamiliaAsignacionesController;
use App\Modules\Addons\PortalCliente\Controllers\MegaFamiliaSolicitudesController;
use App\Modules\Addons\PortalCliente\Controllers\HijoMegaFamiliaController;
use Illuminate\Support\Facades\Route;

// ── Portal Cliente — rutas bajo /portal ────────────────────────────────────
//
// Item roadmap #144: el mismo bloque de rutas se monta dos veces —
// (1) bajo el prefijo /portal (canónico, como siempre) y
// (2) bajo el dominio dedicado (config('portalcliente.domain'), ej. portal.meganet.mx)
// SIN prefijo, para que el subdominio sirva el portal directo en la raíz
// (portal.meganet.mx/facturas en vez de portal.meganet.mx/portal/facturas).
// Un solo closure = cero duplicación/drift. El mount por dominio usa un
// namespace de nombres de ruta distinto (portal_domain.) para no pisar los
// nombres canónicos `portal.*` que ya usan vistas/JS existentes.

$portalRoutes = function () {

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

    // Item #473: recuperar con código por WhatsApp (404 con portalcliente.recuperar_otp apagado).
    // El throttle lleva prefijo propio: sin él, todas las rutas de una misma IP comparten contador.
    Route::post('/password/solicitar-otp', [PasswordOtpController::class, 'solicitarOtp'])
        ->middleware('throttle:5,1,portal-otp-solicitar')->name('password.solicitar-otp');
    Route::get('/recuperar/verificar', [PasswordOtpController::class, 'showVerificar'])
        ->middleware('throttle:30,1,portal-otp-pantallas')->name('recuperar.verificar');
    Route::post('/password/verificar-otp', [PasswordOtpController::class, 'verificarOtp'])
        ->middleware('throttle:10,1,portal-otp-verificar')->name('password.verificar-otp');
    Route::get('/recuperar/nueva', [PasswordOtpController::class, 'showNueva'])
        ->middleware('throttle:30,1,portal-otp-pantallas')->name('recuperar.nueva');
    Route::post('/password/restablecer', [PasswordOtpController::class, 'restablecer'])
        ->middleware('throttle:10,1,portal-otp-restablecer')->name('password.restablecer');

    // Imágenes de las pantallas de acceso: solo las de la versión publicada (Configuración → Portal Cliente).
    Route::get('/apariencia/imagen/{uuid}', [AparienciaImagenController::class, 'mostrar'])
        ->name('apariencia.imagen');

    // ── Rutas protegidas (guard cliente) ──────────────────────────────────
    Route::middleware(['auth.portal'])->group(function () {

        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('/impersonacion/salir', [\App\Modules\Addons\PortalCliente\Controllers\Admin\ImpersonacionController::class, 'salir'])->name('impersonacion.salir');

        // Dashboard
        Route::get('/',          [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.alt');

        // Mi Plan / Servicio
        Route::get('/mi-plan', [PlanController::class, 'index'])->name('plan');

        // Facturas
        Route::get('/facturas',              [FacturasController::class, 'index'])->name('facturas');
        Route::get('/facturas/{id}',         [FacturasController::class, 'show'])->name('facturas.show');
        Route::post('/facturas/{id}/pagar',  [PortalPagoController::class, 'cobrar'])->name('facturas.pagar');

        // Documento vigente de una factura (FAC-10, #518): Prefactura o Factura (PAGADA) en PDF.
        // Propiedad verificada en el controlador; limitado para no generar PDFs sin control.
        Route::get('/documentos/{id}', [FacturasController::class, 'documento'])
            ->whereNumber('id')->middleware('throttle:20,1')->name('documentos.ver');

        // CFDI ya timbrado (item roadmap #148) — solo consulta/descarga
        Route::get('/facturas/{id}/cfdi/xml', [FacturasController::class, 'downloadCfdiXml'])->name('facturas.cfdi.xml');
        Route::get('/facturas/{id}/cfdi/pdf', [FacturasController::class, 'downloadCfdiPdf'])->name('facturas.cfdi.pdf');

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
        Route::get('/flotas/nuevo', [FlotasController::class, 'nuevo'])->middleware('flotas.portal')->name('flotas.nuevo');
        Route::get('/flotas/{id}', [FlotasController::class, 'ficha'])->whereNumber('id')->middleware('flotas.portal')->name('flotas.ficha');
        Route::get('/flotas/notificaciones', [FlotasController::class, 'notificaciones'])->name('flotas.notificaciones');
        Route::post('/flotas/privacidad', [FlotasController::class, 'aceptarPrivacidad'])->name('flotas.privacidad');

        // Geocercas propias del cliente (item #637) — scopeadas por forClient
        Route::get('/flotas/geocercas',                          [FlotasPanelController::class, 'geocercas'])->name('flotas.geocercas.index');
        // Páginas del panel de Flotas en el portal (#654 F3): mismas pantallas Vue
        Route::get('/flotas/mapa',                               [FlotasPanelController::class, 'mapa'])->middleware('flotas.portal')->name('flotas.mapa');
        Route::get('/flotas/geocercas/nueva',                    [FlotasPanelController::class, 'geocercaForm'])->middleware('flotas.portal')->name('flotas.geocercas.nueva');
        Route::get('/flotas/geocercas/{id}/editar',              [FlotasPanelController::class, 'geocercaForm'])->whereNumber('id')->middleware('flotas.portal')->name('flotas.geocercas.editar');
        Route::get('/flotas/geocercas/{id}',                     [FlotasPanelController::class, 'geocercaShow'])->whereNumber('id')->middleware('flotas.portal')->name('flotas.geocercas.show');
        Route::post('/flotas/geocercas',                         [FlotasGeocercasController::class, 'store'])->name('flotas.geocercas.store');
        Route::put('/flotas/geocercas/{id}',                     [FlotasGeocercasController::class, 'update'])->whereNumber('id')->name('flotas.geocercas.update');
        Route::delete('/flotas/geocercas/{id}',                  [FlotasGeocercasController::class, 'destroy'])->whereNumber('id')->name('flotas.geocercas.destroy');

        // Reglas de alerta propias del cliente (item #644) — origen='cliente', scopeadas por client_id
        Route::get('/flotas/reglas',                             [FlotasReglasController::class, 'pagina'])->name('flotas.reglas.index'); // HTML = pantalla (#663); JSON = lista (#644)
        Route::post('/flotas/reglas',                            [FlotasReglasController::class, 'store'])->name('flotas.reglas.store');
        Route::put('/flotas/reglas/{id}',                        [FlotasReglasController::class, 'update'])->whereNumber('id')->name('flotas.reglas.update');
        Route::delete('/flotas/reglas/{id}',                     [FlotasReglasController::class, 'destroy'])->whereNumber('id')->name('flotas.reglas.destroy');
        Route::post('/flotas/reglas/{id}/toggle',                [FlotasReglasController::class, 'toggle'])->whereNumber('id')->name('flotas.reglas.toggle');

        // Contactos de alerta + bitácora de envíos del cliente (#672, P5c)
        Route::get('/flotas/contactos',                          [FlotasContactosController::class, 'pagina'])->name('flotas.contactos.index');
        Route::post('/flotas/contactos',                         [FlotasContactosController::class, 'store'])->name('flotas.contactos.store');
        Route::put('/flotas/contactos/{id}',                     [FlotasContactosController::class, 'update'])->whereNumber('id')->name('flotas.contactos.update');
        Route::delete('/flotas/contactos/{id}',                  [FlotasContactosController::class, 'destroy'])->whereNumber('id')->name('flotas.contactos.destroy');

        // Historial de alertas (#645)
        Route::get('/flotas/alertas',                            [FlotasAlertasController::class, 'index'])->name('flotas.alertas.index');
        Route::post('/flotas/alertas/vista',                     [FlotasAlertasController::class, 'marcarVista'])->name('flotas.alertas.vista');
        Route::post('/flotas/alertas/{id}/vista',                [FlotasAlertasController::class, 'marcarVista'])->whereNumber('id')->name('flotas.alertas.vista.una');

        // Mi Flota = panel de Flotas (#652 F1): espejo de las APIs del panel, MISMOS controladores, en
        // contexto portal ('flotas.portal': guard cliente + suscripción usable). Sin proveedores,
        // comparativo, suscripción, asignaciones, OCR, reglas ni notificaciones (F4/F5).
        Route::prefix('flotas/api')->middleware('flotas.portal')->group(function () {
            Route::prefix('vehiculos')->group(function () {
                Route::get('/',                [PFleetVehicle::class, 'index']);
                Route::post('/',               [PFleetVehicle::class, 'store']);
                Route::get('/data/dashboard',  [PFleetVehicle::class, 'dashboard']);
                Route::get('/data/costo-km',   [PFleetVehicle::class, 'costoKm']);
                Route::get('/data/alertas',    [PFleetVehicle::class, 'alertas']);
                Route::get('/{id}/prediccion-servicios', [PFleetMaintenance::class, 'prediction'])->whereNumber('id');
                Route::get('/{id}/gps',          [PFleetGps::class, 'status'])->whereNumber('id');
                Route::get('/{id}/gps/history',  [PFleetGps::class, 'history'])->whereNumber('id');
                Route::get('/{id}/geofence-events', [PFleetGps::class, 'geofenceEvents'])->whereNumber('id');
                Route::get('/{id}',    [PFleetVehicle::class, 'show'])->whereNumber('id');
                Route::patch('/{id}',  [PFleetVehicle::class, 'update'])->whereNumber('id');
                Route::delete('/{id}', [PFleetVehicle::class, 'destroy'])->whereNumber('id');
            });
            Route::get('gps/flota', [PFleetGps::class, 'fleet']);
            // Mis operadores + asignaciones del cliente (#676 F6b): scopeado por client_id del guard cliente.
            Route::prefix('operadores')->group(function () {
                Route::get('/',        [FlotasOperadoresController::class, 'index']);
                Route::post('/',       [FlotasOperadoresController::class, 'store']);
                Route::put('/{id}',    [FlotasOperadoresController::class, 'update'])->whereNumber('id');
                Route::delete('/{id}', [FlotasOperadoresController::class, 'destroy'])->whereNumber('id');
            });
            Route::get('vehiculos/{id}/asignaciones',  [FlotasOperadoresController::class, 'asignaciones'])->whereNumber('id');
            Route::post('vehiculos/{id}/asignaciones', [FlotasOperadoresController::class, 'asignar'])->whereNumber('id');
            Route::get('plan', [\App\Modules\Addons\PortalCliente\Controllers\FlotasPlanController::class, 'show']);
            Route::prefix('geocercas')->group(function () {
                Route::get('/',        [PFleetGeofence::class, 'index']);
                Route::post('/',       [PFleetGeofence::class, 'store']);
                Route::get('/{id}',    [PFleetGeofence::class, 'show'])->whereNumber('id');
                Route::put('/{id}',    [PFleetGeofence::class, 'update'])->whereNumber('id');
                Route::patch('/{id}',  [PFleetGeofence::class, 'update'])->whereNumber('id');
                Route::delete('/{id}', [PFleetGeofence::class, 'destroy'])->whereNumber('id');
                Route::post('/{id}/vehiculos', [PFleetGeofence::class, 'assignVehicles'])->whereNumber('id');
            });
            // Reglas de alerta del cliente (#663 F4a): MISMO controlador del portal (#644), sin duplicar validaciones.
            Route::prefix('reglas')->group(function () {
                Route::get('/',              [FlotasReglasController::class, 'index']);
                Route::post('/',             [FlotasReglasController::class, 'store']);
                Route::put('/{id}',          [FlotasReglasController::class, 'update'])->whereNumber('id');
                Route::delete('/{id}',       [FlotasReglasController::class, 'destroy'])->whereNumber('id');
                Route::post('/{id}/toggle',  [FlotasReglasController::class, 'toggle'])->whereNumber('id');
            });
            Route::prefix('mantenimientos')->group(function () {
                Route::get('/',        [PFleetMaintenance::class, 'index']);
                Route::post('/',       [PFleetMaintenance::class, 'store']);
                Route::get('/{id}',    [PFleetMaintenance::class, 'show'])->whereNumber('id');
                Route::patch('/{id}',  [PFleetMaintenance::class, 'update'])->whereNumber('id');
                Route::delete('/{id}', [PFleetMaintenance::class, 'destroy'])->whereNumber('id');
                Route::post('/{id}/files', [PFleetMaintenance::class, 'uploadFile'])->whereNumber('id');
            });
            Route::prefix('documentos')->group(function () {
                Route::get('/',                 [PFleetDocument::class, 'index']);
                Route::post('/',                [PFleetDocument::class, 'store']);
                Route::get('/alertas/proximos', [PFleetDocument::class, 'proximos']);
                Route::get('/dashboard/all',    [PFleetDocument::class, 'dashboard']);
                Route::get('/{id}',             [PFleetDocument::class, 'show'])->whereNumber('id');
                Route::post('/{id}',            [PFleetDocument::class, 'update'])->whereNumber('id');
                Route::patch('/{id}',           [PFleetDocument::class, 'update'])->whereNumber('id');
                Route::delete('/{id}',          [PFleetDocument::class, 'destroy'])->whereNumber('id');
                Route::get('/{id}/descargar',   [PFleetDocument::class, 'download'])->whereNumber('id');
                Route::get('/{id}/renovar/prefill', [PFleetDocument::class, 'renew'])->whereNumber('id');
            });
            Route::prefix('combustible')->group(function () {
                Route::get('/',        [PFleetFuel::class, 'index']);
                Route::post('/',       [PFleetFuel::class, 'store']);
                Route::delete('/{id}', [PFleetFuel::class, 'destroy'])->whereNumber('id');
            });
            // Bitácora de notificaciones (#664): solo lectura; el reenvío es del panel (no hay ruta resend aquí).
            Route::get('notificaciones-log', [PFleetNotification::class, 'log']);
            Route::prefix('fotos')->group(function () {
                Route::post('/',       [PFleetPhoto::class, 'store']);
                Route::delete('/{id}', [PFleetPhoto::class, 'destroy'])->whereNumber('id');
            });
        });

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

        // G6: Tareas a nivel de cuenta + asignaciones por perfil
        Route::post('/megafamilia/tareas',                       [MegaFamiliaAsignacionTareasController::class, 'store'])->name('megafamilia.tareas.store');
        Route::delete('/megafamilia/tareas/{id}',                [MegaFamiliaAsignacionTareasController::class, 'destroy'])->name('megafamilia.tareas.destroy');
        Route::post('/megafamilia/asignaciones/{id}/completar',  [MegaFamiliaAsignacionesController::class, 'completar'])->name('megafamilia.asignaciones.completar');

        // G6: Recompensas (catálogo + canje)
        Route::post('/megafamilia/perfiles/{profile}/recompensas',       [MegaFamiliaGamificacionController::class, 'storeRecompensa'])->name('megafamilia.recompensas.store');
        Route::delete('/megafamilia/perfiles/{profile}/recompensas/{id}',[MegaFamiliaGamificacionController::class, 'destroyRecompensa'])->name('megafamilia.recompensas.destroy');
        Route::post('/megafamilia/recompensas/{reward_id}/canjear',      [MegaFamiliaGamificacionController::class, 'canjearRecompensa'])->name('megafamilia.recompensas.canjear');

        // G7: Solicitudes de permiso
        Route::get('/megafamilia/solicitudes',              [MegaFamiliaSolicitudesController::class, 'index'])->name('megafamilia.solicitudes');
        Route::post('/megafamilia/solicitudes/{id}/aprobar', [MegaFamiliaSolicitudesController::class, 'aprobar'])->name('megafamilia.solicitudes.aprobar');
        Route::post('/megafamilia/solicitudes/{id}/rechazar',[MegaFamiliaSolicitudesController::class, 'rechazar'])->name('megafamilia.solicitudes.rechazar');

        // Vista previa de la app del hijo (read-only, para el papá)
        Route::get('/hijo-megafamilia/{profile_id}', [HijoMegaFamiliaController::class, 'index'])->name('hijo-megafamilia.preview');
    });
};

// (1) Mount canónico — /portal/* (sin cambios de comportamiento ni de nombres)
Route::prefix('portal')->name('portal.')->middleware(['web', 'portal.impersonacion'])->group($portalRoutes);

// (2) Mount por dominio dedicado — item #144, DEV primero (ver deploy/README-portal-dev.md)
Route::domain(config('portalcliente.domain'))->name('portal_domain.')->middleware(['web', 'portal.impersonacion'])->group($portalRoutes);

// ── Configuración → Portal Cliente (panel administrativo, guard web) ─────────
// Presentación administrable de las pantallas de acceso: borrador, vista previa, publicación e
// historial. Cada grupo exige su permiso Spatie (que honra roles); el controlador lo repite.
Route::middleware(['web', 'auth'])
    ->prefix('configuracion/portal-cliente')
    ->name('admin.portal-cliente.apariencia.')
    ->group(function () {
        Route::middleware('can:portal-cliente.apariencia.ver')->group(function () {
            Route::get('/', [AparienciaController::class, 'index'])->name('index');
            Route::get('/estado', [AparienciaController::class, 'estado'])->name('estado');
            Route::get('/versiones', [AparienciaController::class, 'versiones'])->name('versiones');
            Route::get('/imagenes/{uuid}', [AparienciaController::class, 'imagen'])->name('imagen');
            Route::get('/vista-previa/{pantalla}', [AparienciaController::class, 'vistaPrevia'])
                ->whereIn('pantalla', ['login', 'registro', 'recuperar'])
                ->name('vista-previa');
        });

        Route::middleware('can:portal-cliente.apariencia.editar')->group(function () {
            Route::post('/borrador', [AparienciaController::class, 'guardarBorrador'])->name('borrador.guardar');
            Route::delete('/borrador', [AparienciaController::class, 'descartarBorrador'])->name('borrador.descartar');
            Route::post('/imagenes', [AparienciaController::class, 'subirImagen'])
                ->middleware('throttle:30,1')
                ->name('imagen.subir');
            Route::post('/versiones/{version}/restaurar', [AparienciaController::class, 'restaurar'])
                ->whereNumber('version')
                ->name('restaurar');
        });

        Route::post('/publicar', [AparienciaController::class, 'publicar'])
            ->middleware('can:portal-cliente.apariencia.publicar')
            ->name('publicar');
    });

// Item #553 — «Entrar como este cliente» (admin con permiso portal.impersonar)
Route::middleware(['web', 'auth', 'can:portal.impersonar'])
    ->post('/portal-impersonar/{clientId}', [\App\Modules\Addons\PortalCliente\Controllers\Admin\ImpersonacionController::class, 'entrar'])
    ->whereNumber('clientId')->name('portal.impersonar');
