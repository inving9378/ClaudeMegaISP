<?php

use App\Modules\Addons\Empresa\Controllers\EmpresaManualController;
use Illuminate\Support\Facades\Route;

/*
 * Rutas del módulo addon-empresa.
 *
 * - GET  /empresa/manual                     → Manual General de la Empresa (documento autocontenido)
 * - GET  /empresa/manual/pdf                  → Exportar a PDF (contenido publicado)
 * - API bajo /empresa/manual/api/*            → CRUD de capítulos/secciones + publicar (#838)
 *
 * Cargadas por BaseModuleServiceProvider::boot vía loadRoutesFrom, por lo que
 * los grupos `web` / `auth` / `check_route_permission` se aplican explícitamente.
 * Cada acción de escritura vuelve a autorizar en el controller (defensa en
 * profundidad, mismo patrón que el resto del sistema).
 */

Route::middleware(['web', 'auth', 'check_route_permission'])
    ->group(function () {
        Route::get('/empresa/manual', [EmpresaManualController::class, 'view'])->name('empresa.manual');
        Route::get('/empresa/manual/pdf', [EmpresaManualController::class, 'pdf'])->name('empresa.manual.pdf');

        Route::get('/empresa/manual/api/data', [EmpresaManualController::class, 'data']);

        Route::post('/empresa/manual/api/chapters', [EmpresaManualController::class, 'storeChapter']);
        Route::put('/empresa/manual/api/chapters/{id}', [EmpresaManualController::class, 'updateChapter']);
        Route::post('/empresa/manual/api/chapters/{id}/eliminar', [EmpresaManualController::class, 'destroyChapter']);
        Route::post('/empresa/manual/api/chapters/reorder', [EmpresaManualController::class, 'reorderChapters']);

        Route::post('/empresa/manual/api/sections', [EmpresaManualController::class, 'storeSection']);
        Route::put('/empresa/manual/api/sections/{id}', [EmpresaManualController::class, 'updateSection']);
        Route::post('/empresa/manual/api/sections/{id}/eliminar', [EmpresaManualController::class, 'destroySection']);
        Route::post('/empresa/manual/api/sections/reorder', [EmpresaManualController::class, 'reorderSections']);
        Route::post('/empresa/manual/api/sections/{id}/publicar', [EmpresaManualController::class, 'publishSection']);
    });
