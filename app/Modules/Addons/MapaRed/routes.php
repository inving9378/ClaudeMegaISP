<?php

use App\Modules\Addons\MapaRed\Controllers\ConnectionsController;
use App\Modules\Addons\MapaRed\Controllers\DevicesController;
use App\Modules\Addons\MapaRed\Controllers\LayersController;
use App\Modules\Addons\MapaRed\Controllers\MapaRedController;
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
});
