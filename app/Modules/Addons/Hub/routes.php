<?php

use App\Modules\Addons\Hub\Controllers\ApiIntegrationController;
use App\Modules\Addons\Hub\Controllers\ApiIntegrationProviderController;
use App\Modules\Addons\Hub\Controllers\IaModulosController;
use App\Modules\Addons\Hub\Controllers\ServiciosModulosController;
use Illuminate\Support\Facades\Route;

// ── Blade view ────────────────────────────────────────────────────────────────
Route::middleware(['web', 'auth', 'check_route_permission'])->group(function () {
    Route::get('/integraciones', fn() => view('addon-hub::index'))
        ->name('hub.index');
});

// ── API JSON ──────────────────────────────────────────────────────────────────
Route::middleware(['web', 'auth'])->prefix('api/hub')->name('hub.api.')->group(function () {
    Route::get('providers',                     [ApiIntegrationController::class, 'providers'])->name('providers');
    Route::get('provider-catalog',              [ApiIntegrationProviderController::class, 'index'])->name('catalog.index');
    Route::post('provider-catalog',             [ApiIntegrationProviderController::class, 'store'])->name('catalog.store');
    Route::put('provider-catalog/{id}',         [ApiIntegrationProviderController::class, 'update'])->whereNumber('id')->name('catalog.update');
    Route::delete('provider-catalog/{id}',      [ApiIntegrationProviderController::class, 'destroy'])->whereNumber('id')->name('catalog.destroy');
    Route::get('ia-modulos',                    [IaModulosController::class, 'index'])->name('iaModulos.index');
    Route::put('ia-modulos/general/{grupo}',    [IaModulosController::class, 'updateGeneral'])->where('grupo', '[a-z0-9-]+')->name('iaModulos.general.update');
    Route::post('ia-modulos/general/{grupo}/probar', [IaModulosController::class, 'probarGeneral'])->where('grupo', '[a-z0-9-]+')->name('iaModulos.general.probar');
    Route::put('ia-modulos/{clave}',            [IaModulosController::class, 'update'])->where('clave', '[a-z0-9_.]+')->name('iaModulos.update');
    Route::post('ia-modulos/{clave}/probar',    [IaModulosController::class, 'probar'])->where('clave', '[a-z0-9_.]+')->name('iaModulos.probar');
    Route::get('servicios-modulos',             [ServiciosModulosController::class, 'index'])->name('serviciosModulos.index');
    Route::put('servicios-modulos/{clave}',     [ServiciosModulosController::class, 'update'])->where('clave', '[a-z0-9_.]+')->name('serviciosModulos.update');
    Route::post('servicios-modulos/{clave}/probar', [ServiciosModulosController::class, 'probar'])->where('clave', '[a-z0-9_.]+')->name('serviciosModulos.probar');
    Route::get('integrations',                  [ApiIntegrationController::class, 'index'])->name('index');
    Route::post('integrations',                 [ApiIntegrationController::class, 'store'])->name('store');
    Route::get('integrations/{id}',             [ApiIntegrationController::class, 'show'])->name('show');
    Route::put('integrations/{id}',             [ApiIntegrationController::class, 'update'])->name('update');
    Route::delete('integrations/{id}',          [ApiIntegrationController::class, 'destroy'])->name('destroy');
    Route::post('integrations/{id}/validate',   [ApiIntegrationController::class, 'validateKey'])->name('validate');
    Route::post('integrations/{id}/set-default',[ApiIntegrationController::class, 'setDefault'])->name('setDefault');
    Route::post('integrations/{id}/rotate',     [ApiIntegrationController::class, 'rotate'])->name('rotate');
    Route::get('integrations/{id}/logs',        [ApiIntegrationController::class, 'logs'])->name('logs');
    Route::get('integrations/{id}/usage',       [ApiIntegrationController::class, 'usage'])->name('usage');
});
