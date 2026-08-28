<?php

use App\Modules\Addons\DocumentacionCorporativa\Controllers\ConcesionController;
use App\Modules\Addons\DocumentacionCorporativa\Controllers\ExpedienteController;
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
        });
    });
