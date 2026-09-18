<?php

use App\Modules\Core\Documentos\Controllers\DocumentTemplate\DocumentTemplateController;
use App\Modules\Core\Documentos\Controllers\DocumentTypeTemplate\DocumentTypeTemplateController;
use Illuminate\Support\Facades\Route;

/*
 * Rutas del módulo core-documentos.
 *
 * Plantillas de documentos y sus tipos. Sub-namespaces internos preservados
 * (DocumentTemplate / DocumentTypeTemplate) porque cada uno trae su propia
 * lógica de variables, contenido y catálogo.
 *
 * URL prefix `administracion/document_template` y `administracion/document_type_template`
 * preservados para compat con frontend.
 *
 * `web` necesario porque loadRoutesFrom no aplica el grupo automáticamente.
 * `check_route_permission` replica el gating legacy.
 */

Route::middleware(['web', 'auth', 'check_route_permission'])
    ->prefix('administracion')
    ->group(function () {

        Route::prefix('document_template')->group(function () {
            Route::get('/', [DocumentTemplateController::class, 'index']);
            Route::post('/table', [DocumentTemplateController::class, 'table']);
            Route::get('/load_content_template', [DocumentTemplateController::class, 'loadContentTemplate']);
            Route::post('/show_content_template', [DocumentTemplateController::class, 'showContentTemplate']);
            Route::post('/show_content_template/{id}', [DocumentTemplateController::class, 'showContentTemplateById']);
            Route::get('/get_variables', [DocumentTemplateController::class, 'getVariables']);
            Route::post('/add', [DocumentTemplateController::class, 'store']);
            Route::post('/update/{id}', [DocumentTemplateController::class, 'update']);
            Route::post('/destroy/{id}', [DocumentTemplateController::class, 'destroy']);
            Route::post('/get_data_template/{id}', [DocumentTemplateController::class, 'getDataTemplate']);
            Route::get('/acuse/exportar', [DocumentTemplateController::class, 'exportarAcuse']);
        });

        Route::prefix('document_type_template')->group(function () {
            Route::get('/', [DocumentTypeTemplateController::class, 'index']);
            Route::post('/add', [DocumentTypeTemplateController::class, 'store']);
            Route::get('/editar/{id}', [DocumentTypeTemplateController::class, 'edit']);
            Route::post('/update/{id}', [DocumentTypeTemplateController::class, 'update']);
            Route::post('/destroy/{id}', [DocumentTypeTemplateController::class, 'destroy']);
            Route::post('/table', [DocumentTypeTemplateController::class, 'table']);
        });
    });

// Item roadmap #9991221 (Irving, opción 1 de q1): TextTemplate.vue (Previsualizar) lo
// consumen Clientes y CRM, no solo Administración — un usuario Vendedor/Mostrador/TECNICO/
// Almacen sin permiso de Administración recibía 403 al previsualizar una plantilla desde
// la ficha de cliente o de CRM (bug funcional confirmado, NO fuga de permisos — ver
// docs/bitacora/2026-09-18-plantillas-preview-permiso-item-9991221.md). Ruta neutral,
// mismo controller/método (sin duplicar lógica), gateada por su propio permiso
// `plantillas.preview` — la ruta de Administración queda intacta para TemplateManager.vue.
Route::middleware(['web', 'auth', 'check_route_permission'])
    ->post('/plantillas/preview', [DocumentTemplateController::class, 'showContentTemplate']);
