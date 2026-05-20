<?php

use App\Modules\Addons\Mapas\Controllers\Geo\ConnectionsController;
use App\Modules\Addons\Mapas\Controllers\Geo\DevicesController;
use App\Modules\Addons\Mapas\Controllers\Geo\KMZController;
use App\Modules\Addons\Mapas\Controllers\Geo\LayersController;
use App\Modules\Addons\Mapas\Controllers\Geo\ProyectsController;
use App\Modules\Addons\Mapas\Controllers\Geo\ServiceBoxController;
use App\Modules\Addons\Mapas\Controllers\Mapas\ActiveEquipmentController;
use App\Modules\Addons\Mapas\Controllers\Mapas\ActiveEquipmentTypeController;
use App\Modules\Addons\Mapas\Controllers\Mapas\BoxController;
use App\Modules\Addons\Mapas\Controllers\Mapas\BoxInputController;
use App\Modules\Addons\Mapas\Controllers\Mapas\BoxTypeController;
use App\Modules\Addons\Mapas\Controllers\Mapas\BrandController;
use App\Modules\Addons\Mapas\Controllers\Mapas\BufferController;
use App\Modules\Addons\Mapas\Controllers\Mapas\CardController;
use App\Modules\Addons\Mapas\Controllers\Mapas\EquipmentLinkController;
use App\Modules\Addons\Mapas\Controllers\Mapas\FiberController;
use App\Modules\Addons\Mapas\Controllers\Mapas\MapasController;
use App\Modules\Addons\Mapas\Controllers\Mapas\MaplinkController;
use App\Modules\Addons\Mapas\Controllers\Mapas\MapProyectController;
use App\Modules\Addons\Mapas\Controllers\Mapas\MapRouteController;
use App\Modules\Addons\Mapas\Controllers\Mapas\PassiveEquipmentController;
use App\Modules\Addons\Mapas\Controllers\Mapas\PassiveEquipmentTypeController;
use App\Modules\Addons\Mapas\Controllers\Mapas\PointAccessoryController;
use App\Modules\Addons\Mapas\Controllers\Mapas\PointController;
use App\Modules\Addons\Mapas\Controllers\Mapas\PoleAccessoryController;
use App\Modules\Addons\Mapas\Controllers\Mapas\PoleController;
use App\Modules\Addons\Mapas\Controllers\Mapas\PortController;
use App\Modules\Addons\Mapas\Controllers\Mapas\RackController;
use App\Modules\Addons\Mapas\Controllers\Mapas\SiteController;
use App\Modules\Addons\Mapas\Controllers\Mapas\SplitterController;
use App\Modules\Addons\Mapas\Controllers\Mapas\TableController;
use App\Modules\Addons\Mapas\Controllers\Mapas\TransceiverController;
use App\Modules\Addons\Mapas\Controllers\Mapas\TrayController;
use App\Modules\Addons\Mapas\Controllers\Mapas\TrenchController;
use App\Modules\Addons\Mapas\Controllers\Mapas\TrenchTypeController;
use App\Modules\Addons\Mapas\Controllers\Mapas\TubeController;
use App\Modules\Addons\Mapas\Controllers\Mapas\TubeTypeController;
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

// ---------------------------------------------------------------
// Mapas (infraestructura física FTTH) — migrado de routes/web.php
// namespace Mapas, prefix `mapas/*`
// ---------------------------------------------------------------
Route::middleware(['web', 'auth', 'check_route_permission'])->prefix('mapas')->group(function () {
    Route::get('/', [MapasController::class, 'index']);
    Route::post('/get_form', [MapasController::class, 'getForm'])->name('maps.getForm');
    Route::post('/object/create', [MapasController::class, 'objectCreate'])->name('maps.objectCreate');
    Route::post('/objects/get', [MapasController::class, 'getObject'])->name('maps.getObject');
    Route::post('/info_window', [MapasController::class, 'getInfoInfoWindow'])->name('maps.getInfoInfoWindow');
    Route::post('/data_form', [MapasController::class, 'getDataForm'])->name('maps.getDataForm');
    Route::post('/data_form/get_by_id', [MapasController::class, 'getDataFormById'])->name('maps.getDataFormById');
    Route::post('/catalog_form', [MapasController::class, 'getCatalogView'])->name('maps.get.catalog.view');

    Route::post('/site/poles', [MapasController::class, 'getPolesBySite'])->name('maps.getPolesBySite');

    Route::post('/object/position/update', [MapasController::class, 'updatePosition'])->name('maps.updatePosition');
    Route::post('/assing/list/type', [MapasController::class, 'objectListForSelectByType'])->name('maps.objectListForSelectByType');
    Route::post('/assing/list/pole', [MapasController::class, 'getAssingListPole'])->name('maps.getAssingListPole');

    Route::post('/search/inputs_or_ports', [MapasController::class, 'searchInputsOrPorts'])->name('maps.searchInputsOrPorts');

    // POINT
    Route::post('point/assign', [MapasController::class, 'assingPoint'])->name('maps.assingPoint');
    Route::post('point/destroy', [PointController::class, 'destroy'])->name('maps.point.destroy');

    // BRANDS
    Route::post('brand/index', [BrandController::class, 'index'])->name('maps.brand.index');
    Route::post('brand/store', [BrandController::class, 'store'])->name('maps.brand.store');
    Route::post('brand/update', [BrandController::class, 'update'])->name('maps.brand.update');
    Route::post('brand/list', [BrandController::class, 'getListToSelect'])->name('maps.brand.list');

    // BOXES
    Route::post('box/index', [BoxController::class, 'index'])->name('maps.box.index');
    Route::post('box/store', [BoxController::class, 'store'])->name('maps.box.store');
    Route::post('box/destroy', [BoxController::class, 'destroy'])->name('maps.box.destroy');

    Route::post('box_type/index', [BoxTypeController::class, 'index'])->name('maps.box_type.index');
    Route::post('box_type/store', [BoxTypeController::class, 'store'])->name('maps.box_type.store');
    Route::post('box_type/update', [BoxTypeController::class, 'update'])->name('maps.box_type.update');

    // BOX INPUTS
    Route::post('box_inputs/index', [BoxInputController::class, 'index'])->name('maps.box_input.index');
    Route::post('box_inputs/list', [BoxInputController::class, 'list'])->name('maps.box_input.list');
    Route::post('box_inputs/store', [BoxInputController::class, 'store'])->name('maps.box_input.store');
    Route::post('box_inputs/update', [BoxInputController::class, 'update'])->name('maps.box_input.update');
    Route::post('box_inputs/search', [BoxInputController::class, 'search'])->name('maps.box_input.search');
    Route::post('box_inputs/list_for_fusion', [BoxInputController::class, 'listForFusion'])->name('maps.box_input.list_for_fusion');
    Route::post('box_inputs/list_for_splitter_in', [BoxInputController::class, 'listForSplitterIn'])->name('maps.box_input.list_for_splitter_in');

    // TRENCHES
    Route::post('trench/index', [TrenchController::class, 'index'])->name('maps.trench.index');
    Route::post('trench/store', [TrenchController::class, 'store'])->name('maps.trench.store');
    Route::post('trench/update', [TrenchController::class, 'update'])->name('maps.trench.update');
    Route::post('trench/destroy', [TrenchController::class, 'destroy'])->name('maps.trench.destroy');

    // TRENCH TYPES
    Route::post('trench_type/index', [TrenchTypeController::class, 'index'])->name('maps.trench_type.index');
    Route::post('trench_type/store', [TrenchTypeController::class, 'store'])->name('maps.trench_type.store');
    Route::post('trench_type/update', [TrenchTypeController::class, 'update'])->name('maps.trench_type.update');

    // TUBE TYPES
    Route::post('tube_type/index', [TubeTypeController::class, 'index'])->name('maps.tube_type.index');
    Route::post('tube_type/store', [TubeTypeController::class, 'store'])->name('maps.tube_type.store');
    Route::post('tube_type/update', [TubeTypeController::class, 'update'])->name('maps.tube_type.update');

    // TUBES
    Route::post('tube/index', [TubeController::class, 'index'])->name('maps.tube.index');
    Route::post('tube/store', [TubeController::class, 'store'])->name('maps.tube.store');

    // POINT ACCESSORIES
    Route::post('point_accessory/index', [PointAccessoryController::class, 'index'])->name('maps.point_accessory.index');
    Route::post('point_accessory/store', [PointAccessoryController::class, 'store'])->name('maps.point_accessory.store');
    Route::post('point_accessory/update', [PointAccessoryController::class, 'update'])->name('maps.point_accessory.update');
    Route::post('point_accessory/destroy', [PointAccessoryController::class, 'destroy'])->name('maps.point_accessory.destroy');

    // MAP_LINKS
    Route::post('/map_link/index', [MaplinkController::class, 'index'])->name('maps.map_link.index');
    Route::post('/map_link/store', [MaplinkController::class, 'store'])->name('maps.map_link.store');
    Route::post('/map_link/show', [MaplinkController::class, 'show'])->name('maps.map_link.show');

    Route::post('/maplinks/list', [MapasController::class, 'getListMapLinks'])->name('maps.getListMapLinks');
    Route::post('/maplinks/route/store', [MaplinkController::class, 'storeMapLinkRoute'])->name('maps.map_link.route.store');
    Route::post('/maplinks/destroy', [MaplinkController::class, 'destroy'])->name('maps.map_link.destroy');

    // MAP
    Route::post('/map_three/list', [MapasController::class, 'getListMapthree'])->name('maps.three.list');
    Route::post('/set_session_position', [MapasController::class, 'setSessionPosition'])->name('maps.session_position.set');
    Route::post('/fiber_cut', [MapasController::class, 'fiberCutStore'])->name('maps.fiber_cut.store');
    Route::post('/fiber_cut/update', [MapasController::class, 'fiberCutUpdate'])->name('maps.fiber_cut.update');
    Route::post('/fiber_cut/destroy', [MapasController::class, 'fiberCutDestroy'])->name('maps.cut_fiber.destroy');

    // CONNECTIONS (fusions / fiber / splitter)
    Route::post('fusions/update/', [EquipmentLinkController::class, 'fusionUpdate'])->name('maps.fusion.update');
    Route::post('fiber_connection/update/', [EquipmentLinkController::class, 'fiberConnectionUpdate'])->name('maps.fiber_connection.update');
    Route::post('splitter_in/update/', [EquipmentLinkController::class, 'splitterInConnectionUpdate'])->name('maps.splitter_in_connection.update');
    Route::post('splitter_out/update/', [EquipmentLinkController::class, 'splitterOutConnectionUpdate'])->name('maps.splitter_out_connection.update');

    // MAP_ROUTE
    Route::post('map_route/index', [MapRouteController::class, 'index'])->name('maps.map_route.index');
    Route::post('map_route/create', [MapRouteController::class, 'create'])->name('maps.map_route.create');
    Route::post('map_route/store', [MapRouteController::class, 'store'])->name('maps.map_route.store');
    Route::post('map_route/show', [MapRouteController::class, 'show'])->name('maps.map_route.show');
    Route::post('map_route/update', [MapRouteController::class, 'update'])->name('maps.map_route.update');

    // RACK
    Route::post('rack/index', [RackController::class, 'index'])->name('maps.rack.index');
    Route::post('rack/store', [RackController::class, 'store'])->name('maps.rack.store');
    Route::post('rack/update/', [RackController::class, 'update'])->name('maps.rack.update');
    Route::post('rack/destroy', [RackController::class, 'destroy'])->name('maps.rack.destroy');
    Route::post('rack/list/', [RackController::class, 'getListToSelect'])->name('maps.rack.list');

    // ACTIVE_EQUIPMENT
    Route::post('active_equipment/index', [ActiveEquipmentController::class, 'index'])->name('maps.active_equipment.index');
    Route::post('active_equipment/store', [ActiveEquipmentController::class, 'store'])->name('maps.active_equipment.store');
    Route::post('active_equipment/update', [ActiveEquipmentController::class, 'update'])->name('maps.active_equipment.update');
    Route::post('active_equipment/destroy', [ActiveEquipmentController::class, 'destroy'])->name('maps.active_equipment.destroy');

    // ACTIVE_EQUIPMENT_TYPE
    Route::post('active_equipment_types/index', [ActiveEquipmentTypeController::class, 'index'])->name('maps.active_equipment_type.index');
    Route::post('active_equipment_types/store', [ActiveEquipmentTypeController::class, 'store'])->name('maps.active_equipment_type.store');
    Route::post('active_equipment_types/update', [ActiveEquipmentTypeController::class, 'update'])->name('maps.active_equipment_type.update');

    // BUFFER
    Route::post('buffer/list', [BufferController::class, 'list'])->name('maps.buffer.list');
    Route::post('buffer/list_by_input_box', [BufferController::class, 'listByInputBox'])->name('maps.buffer.list_by_input_box');

    // FIBER
    Route::post('fiber/list', [FiberController::class, 'list'])->name('maps.fiber.list');
    Route::post('fiber/list_by_input_box', [FiberController::class, 'listByInputBox'])->name('maps.fiber.list_by_input_box');

    // PASSIVE_EQUIPMENT
    Route::post('passive_equipment/index', [PassiveEquipmentController::class, 'index'])->name('maps.passive_equipment.index');
    Route::post('passive_equipment/store', [PassiveEquipmentController::class, 'store'])->name('maps.passive_equipment.store');
    Route::post('passive_equipment/update', [PassiveEquipmentController::class, 'update'])->name('maps.passive_equipment.update');
    Route::post('passive_equipment/destroy', [PassiveEquipmentController::class, 'destroy'])->name('maps.passive_equipment.destroy');

    // PASSIVE_EQUIPMENT_TYPE
    Route::post('passive_equipment_types/index', [PassiveEquipmentTypeController::class, 'index'])->name('maps.passive_equipment_type.index');
    Route::post('passive_equipment_types/store', [PassiveEquipmentTypeController::class, 'store'])->name('maps.passive_equipment_type.store');
    Route::post('passive_equipment_types/update', [PassiveEquipmentTypeController::class, 'update'])->name('maps.passive_equipment_type.update');

    // CARD
    Route::post('card/index', [CardController::class, 'index'])->name('maps.card.index');
    Route::post('card/store', [CardController::class, 'store'])->name('maps.card.store');
    Route::post('card/update', [CardController::class, 'update'])->name('maps.card.update');
    Route::post('card/list', [CardController::class, 'list'])->name('maps.card.list');
    Route::post('card/destroy', [CardController::class, 'destroy'])->name('maps.card.destroy');

    // PORT
    Route::post('port/index', [PortController::class, 'index'])->name('maps.port.index');
    Route::post('port/store', [PortController::class, 'store'])->name('maps.port.store');
    Route::post('port/search', [PortController::class, 'search'])->name('maps.port.search');
    Route::post('port/update/', [PortController::class, 'update'])->name('maps.port.update');
    Route::post('port/passive_equipment/index', [PortController::class, 'passiveEquipmentIndex'])->name('maps.port.passive_equipment.index');
    Route::post('port/passive_equipment/show', [PortController::class, 'passiveEquipmentShow'])->name('maps.port.passive_equipment.show');
    Route::post('port/list/object', [PortController::class, 'listByObject'])->name('maps.port.list.object.index');
    Route::post('port/destroy', [PortController::class, 'destroy'])->name('maps.port.destroy');

    // EQUIPMENT_LINKS
    Route::post('equipment_links/index', [EquipmentLinkController::class, 'index'])->name('maps.equipment_link.index');
    Route::post('equipment_links/store', [EquipmentLinkController::class, 'store'])->name('maps.equipment_link.store');

    // SITE
    Route::post('site/update/', [SiteController::class, 'update'])->name('maps.site.update');
    Route::post('site/list/', [SiteController::class, 'getListToSelect'])->name('maps.site.list');
    Route::post('site/destroy', [SiteController::class, 'destroy'])->name('maps.site.destroy');

    // POLE
    Route::post('pole/update/', [PoleController::class, 'update'])->name('maps.pole.update');
    Route::post('pole/destroy', [PoleController::class, 'destroy'])->name('maps.pole.destroy');

    // POLE ACCESSORY
    Route::post('pole_accessory/index/', [PoleAccessoryController::class, 'index'])->name('maps.pole_accessory.index');
    Route::post('pole_accessory/store/', [PoleAccessoryController::class, 'store'])->name('maps.pole_accessory.store');
    Route::post('pole_accessory/update/', [PoleAccessoryController::class, 'update'])->name('maps.pole_accessory.update');
    Route::post('pole_accessory/destroy', [PoleAccessoryController::class, 'destroy'])->name('maps.pole_accessory.destroy');

    // MAP PROYECT
    Route::post('map_proyect/store/', [MapProyectController::class, 'store'])->name('maps.map_proyect.store');
    Route::post('map_proyect/list/', [MapProyectController::class, 'getListToSelect'])->name('maps.map_proyect.list');
    Route::post('map_proyect/delete/', [MapProyectController::class, 'destroy'])->name('maps.map_proyect.delete');

    // TRANSCEIVER
    Route::post('transceiver/index', [TransceiverController::class, 'index'])->name('maps.transceiver.index');
    Route::post('transceiver/store', [TransceiverController::class, 'store'])->name('maps.transceiver.store');
    Route::post('transceiver/update', [TransceiverController::class, 'update'])->name('maps.transceiver.update');

    // SPLITTERS
    Route::post('splitter/index', [SplitterController::class, 'index'])->name('maps.splitter.index');
    Route::post('splitter/store', [SplitterController::class, 'store'])->name('maps.splitter.store');
    Route::post('splitter/update', [SplitterController::class, 'update'])->name('maps.splitter.update');
    Route::post('splitter/destroy', [SplitterController::class, 'destroy'])->name('maps.splitter.destroy');
    Route::post('splitter/list', [SplitterController::class, 'list'])->name('maps.splitter.list');

    // TRAY
    Route::post('tray/index', [TrayController::class, 'index'])->name('maps.tray.index');
    Route::post('tray/list', [TrayController::class, 'list'])->name('maps.tray.list');
    Route::post('tray/store', [TrayController::class, 'store'])->name('maps.tray.store');
    Route::post('tray/update', [TrayController::class, 'update'])->name('maps.tray.update');

    // TABLE
    Route::post('table', [TableController::class, 'getByName'])->name('maps.table');
});
