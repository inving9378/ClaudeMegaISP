<?php

use App\Modules\Addons\SmartImportExport\Controllers\ImportExportController;
use Illuminate\Support\Facades\Route;

/*
 * Rutas del addon Smart Import/Export.
 *
 * Permisos vía check_route_permission (config/route_permission.php):
 *   - smart_import_view / smart_import_execute
 *   - smart_export_view / smart_export_execute
 *
 * URLs mantenidas estables (cualquier link interno o bookmark
 * apunta a /configuracion/smart-* sin cambio).
 */

Route::middleware(['web', 'auth', 'check_route_permission'])
    ->prefix('configuracion')
    ->group(function () {

        // Smart Import (IA) — analiza, previsualiza y ejecuta importaciones en background
        Route::prefix('smart-import')->group(function () {
            Route::get('/',                  [ImportExportController::class, 'importIndex']);
            Route::post('/upload',           [ImportExportController::class, 'upload']);
            Route::post('/preview',          [ImportExportController::class, 'preview']);
            Route::post('/execute',          [ImportExportController::class, 'execute']);
            Route::get('/status/{jobId}',    [ImportExportController::class, 'status']);
        });

        // Smart Export — exportación selectiva por módulos
        Route::prefix('smart-export')->group(function () {
            Route::get('/',                  [ImportExportController::class, 'exportIndex']);
            Route::get('/modules',           [ImportExportController::class, 'modules']);
            Route::post('/generate',         [ImportExportController::class, 'generate']);
            Route::get('/download/{token}',  [ImportExportController::class, 'download']);
        });

        // Bitácora unificada de import/export (persistente en DB)
        Route::prefix('smart-import-export')->group(function () {
            Route::get('/',                       [ImportExportController::class, 'historyIndex']);
            Route::get('/history',                [ImportExportController::class, 'history']);
            Route::delete('/log/{id}',            [ImportExportController::class, 'destroyLog']);
            Route::get('/log/{id}/download',      [ImportExportController::class, 'downloadFromLog']);
        });
    });
