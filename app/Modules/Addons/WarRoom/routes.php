<?php

use App\Models\User;
use App\Modules\Addons\WarRoom\Controllers\ActionItemController;
use App\Modules\Addons\WarRoom\Controllers\DashboardController;
use App\Modules\Addons\WarRoom\Controllers\InsightsController;
use App\Modules\Addons\WarRoom\Controllers\KpiController;
use App\Modules\Addons\WarRoom\Controllers\MeetingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('warroom')->group(function () {

    Route::middleware('permission:warroom.view')->group(function () {

        // Página principal
        Route::get('/', [DashboardController::class, 'index'])->name('warroom.index');

        // Lookups
        Route::get('/api/users', fn () => response()->json(
            User::select('id', 'name', 'email', 'cellphone')->orderBy('name')->get()
        ));

        // KPIs
        Route::get('/api/kpis/{view}/{period?}', [KpiController::class, 'show'])
            ->where('view', 'resumen|finanzas|operaciones|ventas|red|marketing|talento');

        // Insights
        Route::get('/api/insights/{view}/{period?}', [InsightsController::class, 'show']);
        Route::post('/api/insights/{view}/{period?}/regenerate', [InsightsController::class, 'regenerate']);

        // Meetings
        Route::get('/api/meetings/active', [MeetingController::class, 'active']);
        Route::post('/api/meetings/start', [MeetingController::class, 'start']);
        Route::post('/api/meetings/{meeting}/section/next', [MeetingController::class, 'nextSection']);
        Route::post('/api/meetings/{meeting}/section/previous', [MeetingController::class, 'previousSection']);
        Route::post('/api/meetings/{meeting}/pause', [MeetingController::class, 'pause']);
        Route::post('/api/meetings/{meeting}/resume', [MeetingController::class, 'resume']);
        Route::post('/api/meetings/{meeting}/end', [MeetingController::class, 'end']);
        Route::get('/api/meetings/{meeting}/suggestion', [MeetingController::class, 'getSuggestion']);

        // Action Items
        Route::get('/api/action-items', [ActionItemController::class, 'index']);
        Route::post('/api/action-items', [ActionItemController::class, 'store']);
        Route::put('/api/action-items/{actionItem}', [ActionItemController::class, 'update']);
        Route::delete('/api/action-items/{actionItem}', [ActionItemController::class, 'destroy']);
    });
});
