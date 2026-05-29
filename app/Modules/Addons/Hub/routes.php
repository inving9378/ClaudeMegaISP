<?php

use App\Modules\Addons\Hub\Controllers\ApiIntegrationController;
use Illuminate\Support\Facades\Route;

// ── Blade view ────────────────────────────────────────────────────────────────
Route::middleware(['web', 'auth', 'check_route_permission'])->group(function () {
    Route::get('/integraciones', fn() => view('addon-hub::index'))
        ->name('hub.index');
});

// ── API JSON ──────────────────────────────────────────────────────────────────
Route::middleware(['web', 'auth'])->prefix('api/hub')->name('hub.api.')->group(function () {
    Route::get('providers',                     [ApiIntegrationController::class, 'providers'])->name('providers');
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
