<?php

use App\Modules\Addons\DocumentacionCorporativa\Controllers\BitacoraController;
use App\Modules\Addons\DocumentacionCorporativa\Controllers\ConcesionController;
use App\Modules\Addons\DocumentacionCorporativa\Controllers\DcSolicitudController;
use App\Modules\Addons\DocumentacionCorporativa\Controllers\DocumentoController;
use App\Modules\Addons\DocumentacionCorporativa\Controllers\EntregaController;
use App\Modules\Addons\DocumentacionCorporativa\Controllers\ExpedienteController;
use App\Modules\Addons\DocumentacionCorporativa\Controllers\InventarioController;
use App\Modules\Addons\DocumentacionCorporativa\Controllers\OffboardingController;
use App\Modules\Addons\DocumentacionCorporativa\Controllers\OffboardingOtrosItemsController;
use App\Modules\Addons\DocumentacionCorporativa\Controllers\PendienteController;
use App\Modules\Addons\DocumentacionCorporativa\Controllers\PlantillaController;
use App\Modules\Addons\DocumentacionCorporativa\Controllers\RegistroEstructuradoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Documentación Corporativa
|--------------------------------------------------------------------------
| Prefijo propio de addon (patrón de Inversiones/PortalPago). NO cuelga de
| `/administracion`, que es el panel legacy de Core\Usuarios.
|
| `check_route_permission` es fail-closed: las rutas están declaradas en
| config/route_permission.php bajo `documentacion-corporativa.view`. El gate por
| apartado vive en el controlador (una ruta, 14 permisos).
*/
Route::middleware(['web', 'auth', 'check_route_permission'])
    ->prefix('documentacion-corporativa')
    ->name('dc.')
    ->group(function () {
        Route::get('/', [ExpedienteController::class, 'index'])->name('index');

        Route::prefix('api')->group(function () {
            Route::get('/tablero', [ExpedienteController::class, 'tablero'])->name('tablero');
            Route::get('/apartado/{clave}', [ExpedienteController::class, 'apartado'])->name('apartado');

            // Exportación agregada por apartado (Fase 1.5a, item #785) — PDF/Excel
            // con TODOS los conceptos ya resueltos de ese apartado en un solo archivo.
            Route::get('/apartado/{clave}/exportar', [ExpedienteController::class, 'exportarApartado'])->name('apartado.exportar');

            // Detalle nominal de cartera de clientes (Fase 1.5b, item #786) — gateado
            // aparte por `documento.download` + justificación. Ruta de 3 segmentos
            // ('iv/cartera/detalle-nominal'): no colisiona con `{clave}` (1 segmento)
            // ni con `{clave}/exportar` (2 segmentos) de arriba.
            Route::get('/apartado/iv/cartera/detalle-nominal', [ExpedienteController::class, 'carteraDetalleNominal'])->name('apartado.iv.cartera.detalle_nominal');

            // Acuse de avance en PDF con corte a una fecha (item #9990551) —
            // evidencia agregada (global + por apartado) para la mesa directiva.
            // Declarada ANTES de '/apartado/{clave}' no aplica aquí (prefijo distinto,
            // 'acuse' no colisiona con las rutas de apartado).
            Route::get('/acuse/exportar', [ExpedienteController::class, 'exportarAcuse'])->name('acuse.exportar');

            Route::post('/empresa', [ExpedienteController::class, 'cambiarEmpresa'])->name('empresa.cambiar');

            // Apartado XIII — calendario ANTES de {id}: si no, "calendario" se
            // interpretaría como un id numérico y nunca resolvería a este método.
            Route::get('/concesiones/calendario', [ConcesionController::class, 'calendario'])->name('concesiones.calendario');
            Route::get('/concesiones/data/responsables', [ConcesionController::class, 'responsables'])->name('concesiones.responsables');
            Route::get('/concesiones', [ConcesionController::class, 'index'])->name('concesiones.index');
            Route::post('/concesiones', [ConcesionController::class, 'store'])->name('concesiones.store');
            Route::get('/concesiones/{id}', [ConcesionController::class, 'show'])->name('concesiones.show');
            Route::put('/concesiones/{id}', [ConcesionController::class, 'update'])->name('concesiones.update');
            Route::post('/concesiones/{id}/pagos', [ConcesionController::class, 'storePago'])->name('concesiones.pagos.store');
            Route::put('/concesiones/{id}/pagos/{pagoId}/pagar', [ConcesionController::class, 'marcarPagado'])->name('concesiones.pagos.pagar');

            // Bandeja de pendientes (Fase 2b) — data/responsables ANTES de {id}.
            Route::get('/pendientes/data/responsables', [PendienteController::class, 'responsables'])->name('pendientes.responsables');
            Route::get('/pendientes', [PendienteController::class, 'index'])->name('pendientes.index');
            Route::post('/pendientes', [PendienteController::class, 'store'])->name('pendientes.store');
            Route::get('/pendientes/{id}', [PendienteController::class, 'show'])->name('pendientes.show');
            Route::put('/pendientes/{id}', [PendienteController::class, 'update'])->name('pendientes.update');

            // Registros estructurados (Fase 2c) — accionistas, capital, actas, poderes, contratos.
            Route::get('/registros/{recurso}', [RegistroEstructuradoController::class, 'index'])->name('registros.index');
            Route::post('/registros/{recurso}', [RegistroEstructuradoController::class, 'store'])->name('registros.store');
            Route::put('/registros/{recurso}/{id}', [RegistroEstructuradoController::class, 'update'])->name('registros.update');
            Route::delete('/registros/{recurso}/{id}', [RegistroEstructuradoController::class, 'destroy'])->name('registros.destroy');

            // Inventario (Fase 3.2) — activos, activos digitales e inventario de accesos.
            Route::get('/inventario/{recurso}', [InventarioController::class, 'index'])->name('inventario.index');
            Route::post('/inventario/{recurso}', [InventarioController::class, 'store'])->name('inventario.store');
            Route::put('/inventario/{recurso}/{id}', [InventarioController::class, 'update'])->name('inventario.update');
            Route::delete('/inventario/{recurso}/{id}', [InventarioController::class, 'destroy'])->name('inventario.destroy');

            // Plantillas (Fase 2d) — generar el documento de un concepto tipo `plantilla`.
            Route::post('/concepto/{clave}/generar', [PlantillaController::class, 'generar'])->name('concepto.generar');

            // Repositorio documental (Fase 2a) — subir/versionar/descargar/eliminar.
            // 'lote' y '{id}/versiones/{version}/descargar' declarados ANTES de las
            // rutas con solo {id} para que no se interpreten como parte de un id.
            Route::post('/documentos/lote', [DocumentoController::class, 'storeLote'])->name('documentos.lote');
            Route::post('/documentos', [DocumentoController::class, 'store'])->name('documentos.store');
            Route::get('/documentos/{id}/versiones', [DocumentoController::class, 'versiones'])->name('documentos.versiones');
            Route::get('/documentos/{id}/versiones/{version}/descargar', [DocumentoController::class, 'descargarVersion'])->name('documentos.versiones.descargar');
            Route::get('/documentos/{id}/descargar', [DocumentoController::class, 'descargar'])->name('documentos.descargar');
            Route::delete('/documentos/{id}', [DocumentoController::class, 'destroy'])->name('documentos.destroy');

            // Entregas (Fase 5b.3, apartado XIV, item #812) — descarga del ZIP y
            // del acta de una DcEntrega, con bitácora + contador (ver EntregaController).
            Route::get('/entregas/{id}/zip', [EntregaController::class, 'descargarZip'])->name('entregas.zip');
            Route::get('/entregas/{id}/acta', [EntregaController::class, 'descargarActa'])->name('entregas.acta');

            // Solicitudes de información recibidas (Fase 5a, apartado XIV, item #758).
            Route::get('/solicitudes', [DcSolicitudController::class, 'index'])->name('solicitudes.index');
            Route::post('/solicitudes', [DcSolicitudController::class, 'store'])->name('solicitudes.store');
            Route::put('/solicitudes/{id}', [DcSolicitudController::class, 'update'])->name('solicitudes.update');
            Route::delete('/solicitudes/{id}', [DcSolicitudController::class, 'destroy'])->name('solicitudes.destroy');

            // Entregas de una solicitud (Fase 5c.2a, item #834) — armar (POST)
            // y consultar (GET) las DcEntrega de esa solicitud puntual.
            Route::post('/solicitudes/{id}/entregas', [EntregaController::class, 'store'])->name('solicitudes.entregas.store');
            Route::get('/solicitudes/{id}/entregas', [EntregaController::class, 'index'])->name('solicitudes.entregas.index');

            // Bitácora consultable/exportable (Fase 5c, item #760) — data/*
            // y exportar ANTES de la ruta base, mismo criterio que el resto.
            Route::get('/bitacora/data/empresas', [BitacoraController::class, 'empresasFiltro'])->name('bitacora.empresas');
            Route::get('/bitacora/data/usuarios', [BitacoraController::class, 'usuariosFiltro'])->name('bitacora.usuarios');
            Route::get('/bitacora/exportar', [BitacoraController::class, 'exportar'])->name('bitacora.exportar');
            Route::get('/bitacora', [BitacoraController::class, 'index'])->name('bitacora.index');

            // Checklist de offboarding (Fase 5d-1, apartado XII, item #815) —
            // data/colaboradores ANTES de pendientes/revocar, mismo criterio que el resto.
            Route::get('/offboarding/data/colaboradores', [OffboardingController::class, 'colaboradores'])->name('offboarding.colaboradores');
            Route::get('/offboarding/pendientes', [OffboardingController::class, 'pendientes'])->name('offboarding.pendientes');
            Route::post('/offboarding/revocar', [OffboardingController::class, 'revocar'])->name('offboarding.revocar');
            // Checklist de los 6 ítems fijos de offboarding sin tabla propia
            // (Fase 5d-2a, item #839). Backend independiente de #815/#840
            // (UI del apartado XII, aún sin mergear).
            Route::get('/offboarding/otros-items', [OffboardingOtrosItemsController::class, 'index'])->name('offboarding.otros_items.index');
            Route::post('/offboarding/otros-items', [OffboardingOtrosItemsController::class, 'marcar'])->name('offboarding.otros_items.marcar');
        });
    });
