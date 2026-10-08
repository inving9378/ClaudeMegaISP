<?php

use App\Modules\Addons\MonitoreoOficial\Controllers\MonitoringController;
use Illuminate\Support\Facades\Route;

// Addon: gatea por permiso en el controlador (authorize), como el resto de los
// add-ons del sistema. No se añade a config/route_permission.php.
Route::middleware(['web', 'auth'])->prefix('monitoreo-oficial')->group(function () {
    Route::get('/', [MonitoringController::class, 'dashboard'])->name('monitoreo-oficial.dashboard');
    Route::get('/configuracion', [MonitoringController::class, 'configuracion'])->name('monitoreo-oficial.configuracion');
});
