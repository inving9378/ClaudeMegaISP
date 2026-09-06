<?php

use App\Modules\Addons\MapaRed\Controllers\ConnectionsController;
use App\Modules\Addons\MapaRed\Controllers\DevicesController;
use App\Modules\Addons\MapaRed\Controllers\EnlacesServicioController;
use App\Modules\Addons\MapaRed\Controllers\HilosController;
use App\Modules\Addons\MapaRed\Controllers\KMZController;
use App\Modules\Addons\MapaRed\Controllers\LayersController;
use App\Modules\Addons\MapaRed\Controllers\MapaRedController;
use App\Modules\Addons\MapaRed\Controllers\ProyectsController;
use App\Modules\Addons\MapaRed\Controllers\ServiceBoxController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'check_route_permission'])
    ->get('/mapa-red', [MapaRedController::class, 'index'])
    ->name('mapa-red.index');

/*
 * MR-06a-2 (item #9990334) — port de ConnectionsController + DevicesController al
 * namespace MapaRed, apuntando a los modelos mapared_* (NO a los legacy map_*).
 * Rutas NUEVAS y aditivas bajo /mapa-red/api/*; las rutas viejas /maps/* (módulo Mapas)
 * quedan intactas. Gateadas por el mismo permiso mapa_red_view (patrón /mapa-red/**
 * en config/route_permission.php ya las cubre).
 *
 * MR-06a-4 (item #9990336) suma el bloque de LayersController al mismo grupo.
 * `devicesFromRack` NO se porta: no existe en el controller original (ruta rota sin caller).
 */
Route::middleware(['web', 'auth', 'check_route_permission'])->prefix('mapa-red/api')->group(function () {
    Route::get('/zones', [ConnectionsController::class, 'zones'])->name('mapa-red.api.zones');

    Route::post('/connections', [ConnectionsController::class, 'store'])->name('mapa-red.api.connections.store');
    Route::put('/connections/{connection}', [ConnectionsController::class, 'update'])->name('mapa-red.api.connections.update');
    Route::delete('/connections/{connection}', [ConnectionsController::class, 'destroy'])->name('mapa-red.api.connections.destroy');
    Route::post('/connections-multiple/{id}', [ConnectionsController::class, 'connectionsMultiple'])->name('mapa-red.api.connections-multiple');
    Route::post('/connections/cut/{id}', [ConnectionsController::class, 'cutConnections'])->name('mapa-red.api.connections.cut');

    Route::post('/devices', [DevicesController::class, 'store'])->name('mapa-red.api.devices.store');
    Route::put('/devices/{device}', [DevicesController::class, 'update'])->name('mapa-red.api.devices.update');
    Route::delete('/devices/{device}', [DevicesController::class, 'destroy'])->name('mapa-red.api.devices.destroy');
    Route::post('/devices/save-port/{id}', [DevicesController::class, 'savePort'])->name('mapa-red.api.devices.save-port');
    Route::post('/devices/add-ports/{id}', [DevicesController::class, 'addPorts'])->name('mapa-red.api.devices.add-ports');
    Route::post('/devices/change-card-olt-direction/{id}', [DevicesController::class, 'changeCardOLTDirection'])->name('mapa-red.api.devices.change-card-olt-direction');

    /*
     * MR-06a-3 (item #9990335) — port de KMZController. La ruta vieja /maps/kmz (módulo
     * Mapas) queda intacta escribiendo en map_* (sigue siendo el motor vivo hasta que
     * MR-05 migre los datos); esta es aditiva y apunta a mapared_*.
     */
    Route::post('/kmz/{id}', [KMZController::class, 'loadKMZ'])->name('mapa-red.api.kmz.load-with-parent');
    Route::post('/kmz', [KMZController::class, 'loadKMZ'])->name('mapa-red.api.kmz.load');

    Route::get('/layers', [LayersController::class, 'index']);
    Route::post('/layers', [LayersController::class, 'store']);
    Route::put('/layers/{id}', [LayersController::class, 'update']);
    Route::patch('/layers/{id}', [LayersController::class, 'update']);
    Route::delete('/layers/{id}', [LayersController::class, 'destroy']);

    Route::post('/layers/configuration/{id}', [LayersController::class, 'configuration']);
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
    Route::post('/change-classification', [LayersController::class, 'changeClassification']);
    Route::post('/client-to-service-box/{client}/{box}', [LayersController::class, 'addClientToServiceBox']);
    Route::post('/projects/move-marker/{node}/{to}', [LayersController::class, 'moveMarker']);
    Route::post('/projects/move-marker/{node}', [LayersController::class, 'moveMarker']);

    // MR-06a-5 (item #9990337) — port de ProyectsController + ServiceBoxController.
    // EXCLUYE ServiceBoxController::savePort (sin caller en frontend, confirmado MR-01c).
    Route::post('/projects/get-clients', [ProyectsController::class, 'clients'])->name('mapa-red.api.projects.get-clients');
    Route::post('/projects/clients-without-project', [ProyectsController::class, 'clientsWithoutProject'])->name('mapa-red.api.projects.clients-without-project');
    Route::get('/projects', [ProyectsController::class, 'index'])->name('mapa-red.api.projects.index');
    Route::post('/projects', [ProyectsController::class, 'store'])->name('mapa-red.api.projects.store');
    Route::put('/projects/{project}', [ProyectsController::class, 'update'])->name('mapa-red.api.projects.update');
    Route::delete('/projects/{project}', [ProyectsController::class, 'destroy'])->name('mapa-red.api.projects.destroy');
    Route::post('/projects/move-folder/{node}/{to}', [ProyectsController::class, 'moveFolder'])->name('mapa-red.api.projects.move-folder-to');
    Route::post('/projects/move-folder/{node}', [ProyectsController::class, 'moveFolder'])->name('mapa-red.api.projects.move-folder');

    Route::post('/service-box/selected-clients/{id}', [ServiceBoxController::class, 'getSelectedClients'])->name('mapa-red.api.service-box.selected-clients');
    Route::post('/service-box/avaiables-clients', [ServiceBoxController::class, 'getAvaiablesClients'])->name('mapa-red.api.service-box.avaiables-clients');
    Route::post('/service-box/remove-clients', [ServiceBoxController::class, 'removeClients'])->name('mapa-red.api.service-box.remove-clients');
    Route::post('/service-box/remove-client/{id}', [ServiceBoxController::class, 'removeClient'])->name('mapa-red.api.service-box.remove-client');
    Route::post('/service-box/add-clients/{id}', [ServiceBoxController::class, 'addClients'])->name('mapa-red.api.service-box.add-clients');
    Route::post('/service-box/remove-client-from-drop/{id}', [ServiceBoxController::class, 'removeClientFromDrop'])->name('mapa-red.api.service-box.remove-client-from-drop');

    // MR-11 (item #947) — hilos como entidad propia, ocupación por cable.
    Route::get('/cables/{cable}/hilos', [HilosController::class, 'porCable'])->name('mapa-red.api.hilos.por-cable');
    Route::put('/hilos/{id}', [HilosController::class, 'update'])->name('mapa-red.api.hilos.update');

    // MR-14 (item #950) — enlace de servicio cliente/ONT ↔ puerto de NAP ↔ hilo.
    Route::get('/enlaces-servicio/por-nap', [EnlacesServicioController::class, 'porNap'])->name('mapa-red.api.enlaces-servicio.por-nap');
    Route::post('/enlaces-servicio', [EnlacesServicioController::class, 'store'])->name('mapa-red.api.enlaces-servicio.store');
    Route::put('/enlaces-servicio/{id}', [EnlacesServicioController::class, 'update'])->name('mapa-red.api.enlaces-servicio.update');
});
