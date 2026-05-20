<?php

use App\Modules\Addons\GestionRed\Controllers\Network\Ipv4CalculatorController;
use App\Modules\Addons\GestionRed\Controllers\Network\NetworkController;
use App\Modules\Addons\GestionRed\Controllers\Network\NetworkIpController;
use App\Modules\Addons\GestionRed\Controllers\Router\MikrotikConfigController;
use App\Modules\Addons\GestionRed\Controllers\Router\MikrotikController;
use App\Modules\Addons\GestionRed\Controllers\Router\RouterController;
use App\Modules\Addons\GestionRed\Controllers\OLTs\OLTsBillingController;
use App\Modules\Addons\GestionRed\Controllers\OLTs\OLTsCardsController;
use App\Modules\Addons\GestionRed\Controllers\OLTs\OLTsController;
use App\Modules\Addons\GestionRed\Controllers\OLTs\OLTsODBsController;
use App\Modules\Addons\GestionRed\Controllers\OLTs\OLTsOnuController;
use App\Modules\Addons\GestionRed\Controllers\OLTs\OLTsPonPortsController;
use App\Modules\Addons\GestionRed\Controllers\OLTs\OLTsProfilesController;
use App\Modules\Addons\GestionRed\Controllers\OLTs\OLTsTypeONUsController;
use App\Modules\Addons\GestionRed\Controllers\OLTs\OLTsUplinkPortsController;
use App\Modules\Addons\GestionRed\Controllers\OLTs\OLTsVlansController;
use App\Modules\Addons\GestionRed\Controllers\OLTs\OLTsZonesController;
use Illuminate\Support\Facades\Route;

/*
 * Rutas del módulo addon-gestion-red.
 *
 * Agrupa Network (IPv4), Router (Mikrotik) y OLTs.
 *
 * `web` necesario porque loadRoutesFrom no aplica el grupo automáticamente.
 * `check_route_permission` replica el gating legacy (CheckRoutePermission).
 */

Route::middleware(['web', 'auth', 'check_route_permission'])->prefix('red')->group(function () {

    // ---------------------------------------------------------------
    // IPv4 (Network) — migrado de routes/web.php (namespace Network)
    // ---------------------------------------------------------------
    Route::prefix('ipv4')->group(function () {
        Route::post('/add', [NetworkController::class, 'store']);
        Route::get('/listar', [NetworkController::class, 'index']);
        Route::get('/success', [NetworkController::class, 'success']);
        Route::get('/crear', [NetworkController::class, 'create']);
        Route::post('/table', [NetworkController::class, 'table']);
        Route::post('/update/{id}', [NetworkController::class, 'update']);
        Route::post('/destroy/{id}', [NetworkController::class, 'destroy']);
        Route::post('/network/{id}', [NetworkController::class, 'getIpByNetwork']);

        Route::get('/ver/{id}', [NetworkIpController::class, 'show']);
        Route::post('/ip/table', [NetworkIpController::class, 'table']);
        Route::post('/ip/update/{id}', [NetworkIpController::class, 'update']);

        Route::post('/calculator', [Ipv4CalculatorController::class, 'calculator']);
    });

    // ---------------------------------------------------------------
    // Router (Mikrotik) — migrado de routes/web.php (namespace Router)
    // ---------------------------------------------------------------
    Route::prefix('router')->group(function () {
        Route::get('/listar', [RouterController::class, 'index']);
        Route::get('/success/{id}', [RouterController::class, 'success']);
        Route::get('/crear', [RouterController::class, 'create']);
        Route::post('/add', [RouterController::class, 'store']);
        Route::get('/editar/{id}', [RouterController::class, 'edit']);
        Route::post('/update/{id}', [RouterController::class, 'update']);
        Route::post('/destroy/{id}', [RouterController::class, 'destroy']);
        Route::post('/table', [RouterController::class, 'table']);

        Route::prefix('mikrotik')->group(function () {
            Route::get('/crear', [MikrotikController::class, 'create']);
            Route::post('/add', [MikrotikController::class, 'store']);
            Route::get('/editar/{id}', [MikrotikController::class, 'edit']);
            Route::post('/update/{id}', [MikrotikController::class, 'update']);
            Route::post('/crear/{id}', [MikrotikController::class, 'store']);
            Route::post('/destroy/{id}', [MikrotikController::class, 'destroy']);
            Route::post('/table', [MikrotikController::class, 'table']);
            Route::get('/cleantails', [MikrotikController::class, 'clearMikrotikTails']);
            Route::get('/read-notification/{id}', [RouterController::class, 'readNotification']);

            Route::prefix('config')->group(function () {
                Route::get('/editar/{id}', [MikrotikConfigController::class, 'edit']);
                Route::post('/update/{id}', [MikrotikConfigController::class, 'update']);
                Route::post('/crear/{id}', [MikrotikConfigController::class, 'store']);
                Route::post('/destroy/{id}', [MikrotikConfigController::class, 'destroy']);
            });
        });
    });

});

// Rutas Mikrotik globales (sin prefix `red`) — migradas de routes/web.php
Route::middleware(['web', 'auth', 'check_route_permission'])->group(function () {
    Route::post('/status-by-router/{id}', [MikrotikController::class, 'getMikrotikStatus']);
    Route::post('/remove-rules-by-router/{id}', [MikrotikController::class, 'getMikrotikRemoveRules']);
    Route::post('/create-rules-by-router/{id}', [MikrotikController::class, 'getMikrotikCreateRules']);
    Route::post('/request-clone-client-to-mikrotik/{id}', [MikrotikController::class, 'cloneClientToMikrotik']);
});

// ---------------------------------------------------------------
// OLTs — migrado de routes/web.php (namespace OLTs, prefix `olts/*`)
// ---------------------------------------------------------------
Route::middleware(['web', 'auth', 'check_route_permission'])->prefix('olts')->group(function () {
    Route::get('/', [OLTsController::class, 'panel']);
    Route::post('/list', [OLTsController::class, 'oltList']);
    Route::post('/zones', [OLTsController::class, 'zones']);
    Route::post('/type-onus', [OLTsController::class, 'typeONUs']);
    Route::post('/nomenclatures', [OLTsController::class, 'nomenclatures']);
    Route::post('/uptime-env-temp', [OLTsController::class, 'getUptimeEnvTemp']);
    Route::post('/cards/{id}', [OLTsController::class, 'oltCardsDetails']);
    Route::post('/pon-ports/{id}', [OLTsController::class, 'getOltPonPortsDetails']);
    Route::post('/outage-pons/{id}', [OLTsController::class, 'getOutagePons']);
    Route::post('/outage-pons', [OLTsController::class, 'getOutagePons']);
    Route::post('/uplink-ports/{id}', [OLTsController::class, 'getOltUplinkPortsDetails']);
    Route::post('/vlans/{id}', [OLTsController::class, 'getVLANs']);

    Route::post('/dashboard-interruptions', [OLTsController::class, 'getDashboardInterruptions']);
    Route::post('/dashboard-onus-status/{id}', [OLTsController::class, 'getDashboardOnusStatus']);
    Route::post('/dashboard-onus-status', [OLTsController::class, 'getDashboardOnusStatus']);

    Route::get('/dashboard', [OLTsController::class, 'dashboard']);

    Route::prefix('onus')->group(function () {
        Route::post('/create', [OLTsOnuController::class, 'store']);
        Route::post('/sync/{id}', [OLTsOnuController::class, 'sync']);
        Route::post('/get-by-client/{id}', [OLTsOnuController::class, 'getByClient']);
        Route::post('/get-mgmt-ip/{id}', [OLTsOnuController::class, 'getMgmTIp']);
        Route::post('/get-ip-address/{id}', [OLTsOnuController::class, 'getIpAddress']);
        Route::post('/get-signal-and-status/{id}', [OLTsOnuController::class, 'getSignalAndStatus']);
        Route::post('/configure-ethernet-port/{id}', [OLTsOnuController::class, 'configureEhernetPort']);
        Route::post('/configure-wifi-port/{id}', [OLTsOnuController::class, 'configureWifiPort']);
        Route::post('/update-service-port/{id}', [OLTsOnuController::class, 'updateServicePort']);
        Route::post('/update-attached-vlans/{id}', [OLTsOnuController::class, 'changeAttachedVlans']);
        Route::post('/set-onu-voip-port/{id}', [OLTsOnuController::class, 'setOnuVoipPort']);
        Route::post('/update-channel/{id}', [OLTsOnuController::class, 'updateChannel']);
        Route::post('/update-mode/{id}', [OLTsOnuController::class, 'updateMode']);
        Route::delete('/remove/{id}', [OLTsOnuController::class, 'remove']);
        Route::post('/traffic-graph/{id}', [OLTsOnuController::class, 'getTrafficGraph']);
        Route::post('/image/{id}', [OLTsOnuController::class, 'getImageONU']);
        Route::post('/signal-graph/{id}', [OLTsOnuController::class, 'getSignalGrap']);
        Route::post('/update-mgmt-and-vo-ip/{id}', [OLTsOnuController::class, 'updateMgmtAndVoIp']);
        Route::post('/full-status/{id}', [OLTsOnuController::class, 'getFullStatus']);
        Route::post('/running-config/{id}', [OLTsOnuController::class, 'getRunningConfig']);
        Route::post('/update-external-id/{id}', [OLTsOnuController::class, 'updateExternalId']);
        Route::post('/change-web-user-pass/{id}', [OLTsOnuController::class, 'changeWebUserPass']);
        Route::post('/set-catv/{id}', [OLTsOnuController::class, 'setCATV']);
        Route::post('/change-onu-type/{id}', [OLTsOnuController::class, 'changeOnuType']);
        Route::post('/unconfigured', [OLTsOnuController::class, 'getUnconfigured']);
        Route::post('/unconfigured/{id}', [OLTsOnuController::class, 'getUnconfigured']);
        Route::post('/saved-unconfigured', [OLTsOnuController::class, 'getSavedUnconfigured']);
        Route::post('/configured', [OLTsOnuController::class, 'index']);
        Route::post('/configured/{id}', [OLTsOnuController::class, 'index']);

        Route::post('/signal/{sn}', [OLTsController::class, 'getSignalByOnu']);
        Route::post('/enable-disable/{id}', [OLTsController::class, 'enableDisableOnu']);
        Route::post('/resync/{id}', [OLTsController::class, 'resyncOnuConfig']);
        Route::post('/reboot/{id}', [OLTsController::class, 'rebootOnu']);
        Route::post('/move/{id}', [OLTsController::class, 'moveOnu']);
        Route::post('/details/{id}', [OLTsController::class, 'getDetailsByONU']);
        Route::post('/update-location/{id}', [OLTsController::class, 'updateOnuLocation']);
        Route::post('/nomenclatures', [OLTsController::class, 'nomenclaturesFromOnus']);
    });

    Route::prefix('settings')->group(function () {
        Route::post('/billings', [OLTsBillingController::class, 'index']);

        Route::prefix('zones')->group(function () {
            Route::post('/', [OLTsZonesController::class, 'index']);
            Route::post('/store', [OLTsZonesController::class, 'store']);
        });

        Route::prefix('odbs')->group(function () {
            Route::post('/', [OLTsODBsController::class, 'index']);
            Route::post('/store', [OLTsODBsController::class, 'store']);
        });

        Route::prefix('type-onus')->group(function () {
            Route::post('/', [OLTsTypeONUsController::class, 'index']);
            Route::post('/store', [OLTsTypeONUsController::class, 'store']);
        });

        Route::prefix('profiles')->group(function () {
            Route::post('/', [OLTsProfilesController::class, 'index']);
            Route::post('/store', [OLTsProfilesController::class, 'store']);
        });

        Route::prefix('olts')->group(function () {
            Route::post('/', [OLTsController::class, 'index']);
            Route::post('/{id}/cards', [OLTsCardsController::class, 'index']);
            Route::post('/{id}/pon-ports', [OLTsPonPortsController::class, 'index']);
            Route::post('/{id}/uplink-ports', [OLTsUplinkPortsController::class, 'index']);
            Route::post('/{id}/vlans', [OLTsVlansController::class, 'index']);
            Route::post('/{id}/vlans/store', [OLTsVlansController::class, 'store']);
        });
    });
});
