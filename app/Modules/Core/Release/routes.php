<?php

use App\Modules\Core\Release\Controllers\AuditController;
use App\Modules\Core\Release\Controllers\DeploymentController;
use App\Modules\Core\Release\Controllers\ReleaseController;
use App\Modules\Core\Release\Controllers\ReleaseDescriptionController;
use Illuminate\Support\Facades\Route;

/*
 * Rutas del módulo core-release.
 *
 * Changelog/release notes del sistema. Audit-friendly: cada versión es una
 * entrada navegable con descripciones asociadas.
 *
 * `web` necesario porque loadRoutesFrom no aplica el grupo automáticamente.
 * `check_route_permission` replica el gating legacy.
 */

Route::middleware(['web', 'auth', 'check_route_permission'])->prefix('releases')->group(function () {
    Route::get('/', [ReleaseController::class, 'index']);
    Route::get('/{version}', [ReleaseController::class, 'show']);
    Route::post('/store', [ReleaseController::class, 'store']);
    Route::post('/update/{id}', [ReleaseController::class, 'update']);

    // Audit report
    Route::get('/audit/report',           [AuditController::class, 'generate']);
    Route::get('/audit/plan',             [AuditController::class, 'planIndex']);
    Route::post('/audit/plan/{id}/toggle',[AuditController::class, 'planToggle']);
    Route::post('/audit/plan/{id}/note',  [AuditController::class, 'planNote']);

    Route::get('/{releaseId}/descriptions', [ReleaseDescriptionController::class, 'index']);
    Route::post('/description/store', [ReleaseDescriptionController::class, 'store']);
    Route::post('/description/update/{id}', [ReleaseDescriptionController::class, 'update']);
    Route::delete('/description/delete/{id}', [ReleaseDescriptionController::class, 'destroy']);

    // Resumen de changelog con IA
    Route::post('/generate-changelog', [ReleaseController::class, 'generateChangelog']);

    // Deploy pipeline
    Route::get('/deployments',                    [DeploymentController::class, 'index']);
    Route::get('/deployment/{id}/status',         [DeploymentController::class, 'status'])->whereNumber('id');
    Route::post('/deployment/{id}/retry',         [DeploymentController::class, 'retry'])->whereNumber('id');
});
