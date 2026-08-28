<?php

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
        });
    });
