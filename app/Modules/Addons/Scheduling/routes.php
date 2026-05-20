<?php

use App\Modules\Addons\Scheduling\Controllers\ProjectController;
use App\Modules\Addons\Scheduling\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

/*
 * Rutas del módulo addon-scheduling.
 *
 * Project + Task aplanados (decisión arquitectura 2026-05-20).
 *
 * `web` necesario porque loadRoutesFrom no aplica el grupo automáticamente.
 * `check_route_permission` replica el gating legacy.
 */

Route::middleware(['web', 'auth', 'check_route_permission'])->prefix('scheduling')->group(function () {

    Route::prefix('project')->group(function () {
        Route::get('/', [ProjectController::class, 'index']);
        Route::post('/table', [ProjectController::class, 'table']);
        Route::post('/update/{id}', [ProjectController::class, 'update']);
        Route::post('/add', [ProjectController::class, 'store']);
        Route::post('/destroy/{id}', [ProjectController::class, 'destroy']);
    });

    Route::prefix('task')->group(function () {
        Route::get('/', [TaskController::class, 'index']);
        Route::post('/table', [TaskController::class, 'table']);
        Route::post('/update/{id}', [TaskController::class, 'update']);
        Route::post('/add', [TaskController::class, 'store']);
        Route::get('/crear', [TaskController::class, 'create']);
        Route::post('/destroy/{id}', [TaskController::class, 'destroy']);
        Route::get('/editar/{id}', [TaskController::class, 'edit']);
        Route::get('/calendar', [TaskController::class, 'showCalendar']);
        Route::post('/get-list-template-verification-by-task/{id}', [TaskController::class, 'getListTemplateVerification']);
        Route::post('/update-task-to-calendar', [TaskController::class, 'updatetaskToCalenddar']);
        Route::post('/archive/{id}', [TaskController::class, 'archive']);
        Route::post('/unarchive/{id}', [TaskController::class, 'unArchive']);
        Route::post('/add_note/{id}', [TaskController::class, 'addNote']);
        Route::post('/get-notes-by-task/{id}', [TaskController::class, 'getNotesByTask']);
        Route::post('/get-data-task/{id}', [TaskController::class, 'getData']);
        Route::get('/show-archived', [TaskController::class, 'showArchived']);
        Route::get('/read-notification/{id}', [TaskController::class, 'readNotification']);
        Route::post('/unread-notification/{id}', [TaskController::class, 'unreadNotification']);
        Route::post('/download-file/{id}', [TaskController::class, 'download']);
        Route::post('/upload-file/{task}', [TaskController::class, 'uploadFile']);
        Route::post('/remove-file/{task}', [TaskController::class, 'removeFile']);
    });
});
