<?php

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

    Route::get('/{releaseId}/descriptions', [ReleaseDescriptionController::class, 'index']);
    Route::post('/description/store', [ReleaseDescriptionController::class, 'store']);
    Route::post('/description/update/{id}', [ReleaseDescriptionController::class, 'update']);
    Route::delete('/description/delete/{id}', [ReleaseDescriptionController::class, 'destroy']);
});
