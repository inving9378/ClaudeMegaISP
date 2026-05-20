<?php

use App\Modules\Addons\Tickets\Controllers\DashboardController;
use App\Modules\Addons\Tickets\Controllers\TicketController;
use App\Modules\Addons\Tickets\Controllers\TicketThreadController;
use Illuminate\Support\Facades\Route;

/*
 * Rutas del módulo addon-tickets.
 *
 * Sistema de tickets con threads, dashboard, estadísticas y asignaciones.
 *
 * `web` necesario porque loadRoutesFrom no aplica el grupo automáticamente.
 * `check_route_permission` replica el gating legacy.
 */

Route::middleware(['web', 'auth', 'check_route_permission'])->prefix('tickets')->group(function () {
    Route::get('/', [DashboardController::class, 'index']);

    Route::get('/abiertos', [TicketController::class, 'opened']);
    Route::get('/abiertos/{client_id}', [TicketController::class, 'opened']);
    Route::get('/cerrados', [TicketController::class, 'closed']);
    Route::get('/cerrados/{client_id}', [TicketController::class, 'closed']);
    Route::get('/reciclados', [TicketController::class, 'trash']);
    Route::get('/crear', [TicketController::class, 'create']);
    Route::get('/crear/{clientId}', [TicketController::class, 'create']);
    Route::post('/add', [TicketController::class, 'store']);
    Route::get('/success/{id}', [TicketController::class, 'success']);
    Route::get('/editar/{id}', [TicketController::class, 'edit']);
    Route::get('/ver/{id}', [TicketController::class, 'ver']);
    Route::post('/update/{id}', [TicketController::class, 'update']);
    Route::post('/mensaje/update/{id}', [TicketThreadController::class, 'update']);
    Route::post('/mensaje/add/{id}', [TicketThreadController::class, 'store']);
    Route::get('/destroy/{id}', [TicketController::class, 'destroy']);
    Route::post('/table', [TicketController::class, 'table']);
    Route::get('/notifica/{id}', [TicketController::class, 'notificationsReadMarked']);

    Route::post('/get-ticket-by-id/{id}', [TicketController::class, 'getTicketById']);
    Route::post('/get-time-lapsed/{id}', [TicketController::class, 'getTimeLapsed']);
    Route::post('/get-user-data-by-ticket-id/{id}', [TicketController::class, 'getUserDataTicketById']);
    Route::post('/set-status-ticket-by-id/{id}', [TicketController::class, 'setStatusTicketById']);
    Route::post('/get-ticket-thread-by-id/{id}', [TicketThreadController::class, 'getTicketThreadById']);
    Route::post('/get-data-client/{id}', [TicketController::class, 'getDataClient']);
    Route::post('/get-parent-ticket-by-id/{id}', [TicketThreadController::class, 'getParentTicketById']);
    Route::post('/get-child-ticket-by-id/{id}', [TicketThreadController::class, 'getChildTicketById']);

    Route::post('/request-statistics-for-tarjets-by-status', [DashboardController::class, 'getStatisticsForTarjetsByStatus']);
    Route::post('/request-ticket-assigned-to-me', [DashboardController::class, 'getTicketAssignedToMe']);
    Route::post('/request-ticket-assigned-to', [DashboardController::class, 'getTicketAssignedTo']);
    Route::get('/get-tickets-new-by-date/{startDate}/{endDate}/{status}', [DashboardController::class, 'getTicketsByDateAndStatus']);
});
