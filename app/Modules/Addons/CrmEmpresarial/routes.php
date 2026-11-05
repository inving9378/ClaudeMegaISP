<?php

use App\Modules\Addons\CrmEmpresarial\Controllers\ConfiguracionServicioController;
use App\Modules\Addons\CrmEmpresarial\Controllers\ConversionController;
use App\Modules\Addons\CrmEmpresarial\Controllers\DocumentoEmisionController;
use App\Modules\Addons\CrmEmpresarial\Controllers\PropuestaController;
use App\Modules\Addons\CrmEmpresarial\Controllers\SegmentoController;
use Illuminate\Support\Facades\Route;

/*
| CRM Empresarial (item #555). Sin UI todavía: sólo el catálogo de segmentos.
| Rutas declaradas en config/route_permission.php bajo `crm-empresarial.expediente.view`.
*/
Route::middleware(['web', 'auth', 'check_route_permission'])
    ->prefix('crm-empresarial')
    ->name('crme.')
    ->group(function () {
        Route::get('/api/segmentos', [SegmentoController::class, 'index'])->name('segmentos');

        // Pestaña Servicios del expediente (item #601): pantalla + contexto (catálogo, IVA, sitios)
        Route::get('/expedientes/{expediente}/servicios', [ConfiguracionServicioController::class, 'pantalla'])->name('servicios.pantalla');
        Route::get('/api/expedientes/{expediente}/servicios-contexto', [ConfiguracionServicioController::class, 'contexto'])->name('servicios.contexto');

        // Configurador de servicios dedicados (item #559)
        Route::get('/api/expedientes/{expediente}/servicios', [ConfiguracionServicioController::class, 'index'])->name('servicios.index');
        Route::post('/api/expedientes/{expediente}/servicios', [ConfiguracionServicioController::class, 'store'])->name('servicios.store');
        Route::put('/api/expedientes/{expediente}/servicios/{id}', [ConfiguracionServicioController::class, 'update'])->name('servicios.update');
        Route::delete('/api/expedientes/{expediente}/servicios/{id}', [ConfiguracionServicioController::class, 'destroy'])->name('servicios.destroy');

        // Propuestas comerciales con versiones y estados (item #562)
        Route::get('/api/expedientes/{expediente}/propuestas', [PropuestaController::class, 'index'])->name('propuestas.index');
        Route::get('/api/crm/{crm}/propuestas', [PropuestaController::class, 'porCrm'])->name('propuestas.por-crm');
        Route::post('/api/expedientes/{expediente}/propuestas', [PropuestaController::class, 'store'])->name('propuestas.store');
        Route::post('/api/propuestas/{id}/versiones', [PropuestaController::class, 'nuevaVersion'])->name('propuestas.nueva-version');
        Route::put('/api/propuesta-versiones/{id}', [PropuestaController::class, 'editar'])->name('propuestas.editar');
        Route::post('/api/propuesta-versiones/{id}/transicion', [PropuestaController::class, 'transicion'])->name('propuestas.transicion');

        // Conversión a cliente administrativo (item #563)
        Route::get('/api/expedientes/{expediente}/conversion', [ConversionController::class, 'estado'])->name('conversion.estado');
        Route::post('/api/expedientes/{expediente}/conversion', [ConversionController::class, 'convertir'])->name('conversion.convertir');

        // Emisiones de documento (item #604)
        Route::get('/api/emisiones/{uuid}', [DocumentoEmisionController::class, 'show'])->name('emisiones.show');
        Route::get('/api/emisiones/{uuid}/{formato}', [DocumentoEmisionController::class, 'descargar'])->name('emisiones.descargar');
    });
