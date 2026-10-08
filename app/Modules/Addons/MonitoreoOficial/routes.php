<?php

use App\Modules\Addons\MonitoreoOficial\Controllers\MonitoringController;
use Illuminate\Support\Facades\Route;

// Addon: gatea por permiso en el controlador (authorize), como el resto de los
// add-ons del sistema. No se añade a config/route_permission.php.
Route::middleware(['web', 'auth'])->prefix('monitoreo-oficial')->group(function () {
    Route::get('/', [MonitoringController::class, 'dashboard'])->name('monitoreo-oficial.dashboard');
    Route::get('/configuracion', [MonitoringController::class, 'configuracion'])->name('monitoreo-oficial.configuracion');

    // Expedientes (monitoring_cases) — Fase 2
    Route::get('/expedientes/crear', [MonitoringController::class, 'crearExpediente'])->name('monitoreo-oficial.expedientes.crear');
    Route::post('/expedientes', [MonitoringController::class, 'guardarExpediente'])->name('monitoreo-oficial.expedientes.guardar');
    Route::get('/expedientes/{id}/editar', [MonitoringController::class, 'editarExpediente'])->whereNumber('id')->name('monitoreo-oficial.expedientes.editar');
    Route::put('/expedientes/{id}', [MonitoringController::class, 'actualizarExpediente'])->whereNumber('id')->name('monitoreo-oficial.expedientes.actualizar');
    Route::delete('/expedientes/{id}', [MonitoringController::class, 'eliminarExpediente'])->whereNumber('id')->name('monitoreo-oficial.expedientes.eliminar');
    Route::get('/expedientes/{id}', [MonitoringController::class, 'verExpediente'])->whereNumber('id')->name('monitoreo-oficial.expedientes.ver');

    // Documentos del expediente (monitoring_case_documents) — Fase 3 (PDF manual)
    Route::post('/expedientes/{id}/documentos', [MonitoringController::class, 'subirDocumento'])->whereNumber('id')->name('monitoreo-oficial.documentos.subir');
    Route::get('/documentos/{documentId}/descargar', [MonitoringController::class, 'descargarDocumento'])->whereNumber('documentId')->name('monitoreo-oficial.documentos.descargar');
    Route::delete('/documentos/{documentId}', [MonitoringController::class, 'eliminarDocumento'])->whereNumber('documentId')->name('monitoreo-oficial.documentos.eliminar');

    // Fuentes oficiales (monitoring_sources) — Fase 2
    Route::get('/fuentes/crear', [MonitoringController::class, 'crearFuente'])->name('monitoreo-oficial.fuentes.crear');
    Route::post('/fuentes', [MonitoringController::class, 'guardarFuente'])->name('monitoreo-oficial.fuentes.guardar');
    Route::get('/fuentes/{id}/editar', [MonitoringController::class, 'editarFuente'])->whereNumber('id')->name('monitoreo-oficial.fuentes.editar');
    Route::put('/fuentes/{id}', [MonitoringController::class, 'actualizarFuente'])->whereNumber('id')->name('monitoreo-oficial.fuentes.actualizar');
    Route::delete('/fuentes/{id}', [MonitoringController::class, 'eliminarFuente'])->whereNumber('id')->name('monitoreo-oficial.fuentes.eliminar');
});
