<?php

use App\Modules\Core\CRM\Controllers\CrmController;
use App\Modules\Core\CRM\Controllers\CrmInformationController;
use App\Modules\Core\CRM\Controllers\DashboardController;
use App\Modules\Core\CRM\Controllers\DocumentCrmController;
use Illuminate\Support\Facades\Route;

/*
 * Rutas del módulo Core/CRM (core-crm).
 *
 * Origen previo en routes/web.php:
 *   Route::group(['middleware' => ['auth']], function () {
 *     Route::group(['middleware' => ['check_route_permission']], function () {
 *       Route::group(['namespace' => 'Module'], function () {
 *         Route::group(['prefix' => 'crm', 'namespace' => 'Crm'], function () { ... });
 *       });
 *     });
 *   });
 *
 * Se preserva la combinación de middleware (`web` agregado porque
 * loadRoutesFrom no aplica el grupo `web` automáticamente — ver
 * memory/feedback_module_routes_web_middleware.md).
 */

Route::middleware(['web', 'auth', 'check_route_permission'])
    ->prefix('crm')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index']);
        Route::get('/success/{id}', [CrmController::class, 'success']);
        Route::get('/listar', [CrmController::class, 'index'])->name('crm');
        Route::get('/crear', [CrmController::class, 'create']);
        Route::post('/add', [CrmController::class, 'store']);
        Route::get('/editar/{id}', [CrmController::class, 'edit']);
        Route::post('/update/{id}', [CrmInformationController::class, 'update']);
        Route::get('/view-of-convert-crm-to-client/{id}', [CrmController::class, 'viewOfConvertCrmToClient']);
        Route::post('/convert-to-client/{id}', [CrmController::class, 'convertToClient']);
        Route::post('/update-last-contacted/{id}', [CrmController::class, 'updateLastContacted']);
        Route::post('/destroy/{id}', [CrmController::class, 'destroy']);
        Route::post('/table', [CrmController::class, 'table']);
        Route::post('information/{crmId}/get-crm-main-information-id-and-crm-lead-information-id', [CrmController::class, 'getCrmMainInformationIdAndCrmLeadInformationId']);

        Route::group(['prefix' => 'document'], function () {
            Route::post('/add/{idCrm}', [DocumentCrmController::class, 'store']);
            Route::post('/update/{idCrm}', [DocumentCrmController::class, 'update']);
            Route::post('/upload-file/{id}', [DocumentCrmController::class, 'uploadFile']);
            Route::post('/table', [DocumentCrmController::class, 'table']);
            Route::post('/destroy/{id}', [DocumentCrmController::class, 'destroy']);
            Route::post('/generate_contract/{id}', [DocumentCrmController::class, 'generateContract']);
            Route::get('/load_content_template', [DocumentCrmController::class, 'loadContentTemplate']);
            Route::post('/show_content_template', [DocumentCrmController::class, 'showContentTemplate']);
        });
    });
