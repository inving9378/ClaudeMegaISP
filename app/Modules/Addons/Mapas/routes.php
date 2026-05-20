<?php

use App\Modules\Addons\Mapas\Controllers\Geo\ConnectionsController;
use App\Modules\Addons\Mapas\Controllers\Geo\DevicesController;
use App\Modules\Addons\Mapas\Controllers\Geo\KMZController;
use App\Modules\Addons\Mapas\Controllers\Geo\LayersController;
use App\Modules\Addons\Mapas\Controllers\Geo\ProyectsController;
use App\Modules\Addons\Mapas\Controllers\Geo\ServiceBoxController;
use Illuminate\Support\Facades\Route;

/*
 * Rutas del módulo addon-mapas.
 *
 * Agrupa dos sub-dominios:
 *  - Geo (legacy `prefix=maps`): capa georreferencial — KMZ, Layers, Projects,
 *    Connections, Devices, ServiceBox.
 *  - Mapas (legacy `prefix=mapas`, sub-namespace Mapas): infraestructura física
 *    FTTH — Box, Pole, Splitter, Fiber, etc. Pendiente de migrar.
 *
 * `web` necesario porque loadRoutesFrom no aplica el grupo automáticamente.
 * `check_route_permission` replica el gating legacy.
 */

// ---------------------------------------------------------------
// Geo (Maps) — migrado de routes/web.php (namespace Maps, prefix `maps/*`)
// ---------------------------------------------------------------
Route::middleware(['web', 'auth', 'check_route_permission'])->prefix('maps')->group(function () {
    Route::get('/zones', [ConnectionsController::class, 'zones']);
    Route::post('/get-clients', [ProyectsController::class, 'clients']);
    Route::post('/clients-without-project', [ProyectsController::class, 'clientsWithoutProject']);
    Route::resource('/layers', LayersController::class);
    Route::resource('/projects', ProyectsController::class);
    Route::post('/projects/move-folder/{node}/{to}', [ProyectsController::class, 'moveFolder']);
    Route::post('/projects/move-marker/{node}/{to}', [LayersController::class, 'moveMarker']);
    Route::post('/projects/move-folder/{node}', [ProyectsController::class, 'moveFolder']);
    Route::post('/projects/move-marker/{node}', [LayersController::class, 'moveMarker']);
    Route::post('/layers/configuration/{id}', [LayersController::class, 'configuration']);
    Route::post('/projects/move-marker/{node}', [LayersController::class, 'moveMarker']);
    Route::post('/layers/convert-from-project/{id}', [LayersController::class, 'convertLayersFromProject']);
    Route::post('/layers/convert-from-layer/{id}', [LayersController::class, 'convertLayerFromLayer']);
    Route::post('/layers/convert-from-tickeds', [LayersController::class, 'convertLayersFromTickeds']);
    Route::post('/layers/destroy-multiple', [LayersController::class, 'destroyMultiple']);
    Route::post('/layers/coords/{id}', [LayersController::class, 'coords']);
    Route::post('/layers/avaiables-routes/{id}', [LayersController::class, 'avaiablesRoutes']);
    Route::post('/layers/avaiables-routes', [LayersController::class, 'avaiablesRoutes']);
    Route::post('/layers/assign-routes/{id}', [LayersController::class, 'assignRoutes']);
    Route::post('/layers/create-input/{id}', [LayersController::class, 'createInput']);
    Route::post('/layers/update-input/{id}', [LayersController::class, 'updateInput']);
    Route::post('/layers/update-markers-distance-from-route/{id}', [LayersController::class, 'updateMarkersDistanceFromRoute']);
    Route::delete('/layers/unassign-route/{id}', [LayersController::class, 'unassignRoute']);
    Route::post('/layers/change-route-position/{id}', [LayersController::class, 'changeRoutePosition']);
    Route::get('/layers/devices/{id}', [LayersController::class, 'devicesFromRack']);
    Route::post('/change-classification', [LayersController::class, 'changeClassification']);
    Route::post('/client-to-service-box/{client}/{box}', [LayersController::class, 'addClientToServiceBox']);
    Route::post('/kmz/{id}', [KMZController::class, 'loadKMZ']);
    Route::post('/kmz', [KMZController::class, 'loadKMZ']);
    Route::post('/service-box/selected-clients/{id}', [ServiceBoxController::class, 'getSelectedClients']);
    Route::post('/service-box/avaiables-clients', [ServiceBoxController::class, 'getAvaiablesClients']);
    Route::post('/service-box/remove-clients', [ServiceBoxController::class, 'removeClients']);
    Route::post('/service-box/remove-client/{id}', [ServiceBoxController::class, 'removeClient']);
    Route::post('/service-box/add-clients/{id}', [ServiceBoxController::class, 'addClients']);
    Route::post('/service-box/save-port/{id}', [ServiceBoxController::class, 'savePort']);
    Route::post('/service-box/remove-client-from-drop/{id}', [ServiceBoxController::class, 'removeClientFromDrop']);

    // Connections
    Route::resource('/connections', ConnectionsController::class)->except('index');
    Route::post('/connections-multiple/{id}', [ConnectionsController::class, 'connectionsMultiple']);
    Route::post('/connections/cut/{id}', [ConnectionsController::class, 'cutConnections']);

    // Devices
    Route::resource('/devices', DevicesController::class)->except('index');
    Route::post('/devices/save-port/{id}', [DevicesController::class, 'savePort']);
    Route::post('/devices/add-ports/{id}', [DevicesController::class, 'addPorts']);
    Route::post('/devices/change-card-olt-direction/{id}', [DevicesController::class, 'changeCardOLTDirection']);
});
