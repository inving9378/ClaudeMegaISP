<?php

use App\Modules\Addons\Roadmap\Controllers\RoadmapController;
use App\Modules\Addons\Roadmap\Controllers\RoadmapMemoryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])
    ->prefix('api/roadmap')
    ->group(function () {
        Route::get('/items',               [RoadmapController::class, 'index']);
        Route::post('/items',              [RoadmapController::class, 'store']);
        Route::patch('/items/{id}',        [RoadmapController::class, 'update']);
        Route::post('/items/{id}/start',              [RoadmapController::class, 'start']);
        Route::post('/items/{id}/complete',           [RoadmapController::class, 'complete']);
        Route::post('/items/{id}/cancel',             [RoadmapController::class, 'cancel']);
        Route::delete('/items/{id}',                  [RoadmapController::class, 'destroy']);

        // Sub-tareas y bitácora
        Route::patch('/items/{id}/subtasks',                  [RoadmapController::class, 'updateSubtasks']);
        Route::post('/items/{id}/subtasks/{index}/toggle',    [RoadmapController::class, 'toggleSubtask']);
        Route::post('/items/{id}/log',                        [RoadmapController::class, 'addLog']);

        // Memoria CLAUDE.md por item
        Route::get('/items/{id}/memory',          [RoadmapMemoryController::class, 'show']);
        Route::get('/items/{id}/memory/prompt',   [RoadmapMemoryController::class, 'generatePrompt']);
        Route::post('/items/{id}/memory/report',  [RoadmapMemoryController::class, 'appendReport']);
        Route::post('/items/{id}/memory/raw',     [RoadmapMemoryController::class, 'replaceRaw']);
    });
