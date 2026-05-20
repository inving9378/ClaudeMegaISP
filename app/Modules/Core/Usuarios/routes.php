<?php

use App\Modules\Core\Usuarios\Controllers\AdministracionController;
use App\Modules\Core\Usuarios\Controllers\PermissionController;
use App\Modules\Core\Usuarios\Controllers\RolController;
use App\Modules\Core\Usuarios\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
 * Rutas del módulo core-usuarios.
 *
 * Gestión de usuarios, roles, permisos y datos auxiliares de direcciones
 * (states/municipalities/colonies) — estos últimos se sirven desde
 * UserController porque así estaban en legacy.
 *
 * URL prefix `administracion/*` preservado para compatibilidad con frontend.
 *
 * `web` necesario porque loadRoutesFrom no aplica el grupo automáticamente.
 * `check_route_permission` replica el gating legacy.
 */

Route::middleware(['web', 'auth', 'check_route_permission'])->prefix('administracion')->group(function () {

    // Dashboard de Administración (entry point + procesos batch)
    Route::get('/', [AdministracionController::class, 'index']);
    Route::get('/clean-all-client-service', [AdministracionController::class, 'clearAllClientServices']);
    Route::get('/add-clients-imported-to-mikrotik', [AdministracionController::class, 'addClientsImportedToMikrotik']);
    Route::get('/suspend_clients', [AdministracionController::class, 'suspendProcess']);
    Route::get('/billing_services', [AdministracionController::class, 'billigProcess']);
    Route::post('/active-schedule-process', [AdministracionController::class, 'activeCommands']);
    Route::get('/check-schedule-process', [AdministracionController::class, 'checkProcess']);
    Route::get('/show_scripts', [AdministracionController::class, 'showScripts']);
    Route::get('/rectify_address_list', [AdministracionController::class, 'rectifyAddressList']);
    Route::get('/billing_services_to_client_active_promise_payment', [AdministracionController::class, 'billingServiceToClientActivePromise']);

    Route::prefix('user')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('user.index');
        Route::get('/getRoles', [UserController::class, 'getRoles'])->name('user.getRoles');
        Route::get('/get-all-users', [UserController::class, 'getAllUsers'])->name('user.get-all-users');
        Route::get('/crear', [UserController::class, 'create'])->name('user.create');
        Route::post('/create', [UserController::class, 'store'])->name('user.store');
        Route::get('/{id}/editar', [UserController::class, 'edit'])->name('user.edit');
        Route::post('/get-data-user/{id}', [UserController::class, 'getData'])->name('user.getData');
        Route::post('/{id}/update', [UserController::class, 'update'])->name('user.update');
        Route::delete('/{id}/destroy', [UserController::class, 'destroy'])->name('user.destroy');
        Route::post('/{id}/inactive-or-active', [UserController::class, 'inactiveOrActive'])->name('user.inactive-or-active');
    });

    Route::prefix('addresses')->group(function () {
        Route::get('/states', [UserController::class, 'getStates'])->name('states');
        Route::get('/{id}/municipalities', [UserController::class, 'getMunicipalities'])->name('municipalities');
        Route::get('/{id}/colonies', [UserController::class, 'getColonies'])->name('colonies');
    });

    Route::prefix('rol')->group(function () {
        Route::get('/', [RolController::class, 'index']);
        Route::get('/get-all', [RolController::class, 'get']);
        Route::post('/add', [RolController::class, 'store']);
        Route::get('/editar-role/{id}', [RolController::class, 'edit']);
        Route::post('/update-role/{id}', [RolController::class, 'updateRole']);
        Route::delete('/destroy/{id}', [RolController::class, 'destroy']);
        Route::post('/table', [RolController::class, 'table']);
    });

    Route::prefix('permisos')->group(function () {
        Route::get('/get-permission-for-role/{id}', [PermissionController::class, 'get']);
        Route::post('/update-permission-for-role/{id}', [PermissionController::class, 'update']);
        Route::get('/get-permission-for-user/{id}', [PermissionController::class, 'getPermissionUser']);
        Route::post('/update-permission-for-user/{id}', [PermissionController::class, 'updatePermissionUser']);
    });
});

// Rutas globales de permisos (sin prefix `administracion`, sin
// `check_route_permission` por diseño — son los endpoints que VERIFICAN
// permisos, así que no pueden depender de ellos).
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/permissions-auth', [PermissionController::class, 'userPermissions']);
    Route::post('/has-permission-to-view/{view}', [PermissionController::class, 'hasPermissionToView']);
    Route::post('/all-view-has-permission', [PermissionController::class, 'allViewHasPermission']);
});
