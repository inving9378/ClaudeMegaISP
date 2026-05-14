<?php

use App\Modules\Core\Dashboard\Controllers\HomeController;
use App\Modules\Core\Dashboard\Controllers\StaticsController;
use Illuminate\Support\Facades\Route;

/*
 * Rutas del módulo Core/Dashboard.
 *
 * Origen previo en routes/web.php:
 * - /, /index y los endpoints /get-*-card-in-dashboard-c estaban dentro del
 *   grupo middleware ['auth', 'check_route_permission'].
 * - El bloque /statics/* estaba en el mismo grupo.
 * - /home (named 'home') estaba fuera del grupo de check_route_permission
 *   pero el controlador aplica 'auth' por constructor.
 *
 * Se preserva esa distribución de middleware exactamente.
 */

Route::middleware(['auth', 'check_route_permission'])->group(function () {
    Route::get('/', [HomeController::class, 'index']);
    Route::get('/index', [HomeController::class, 'index']);

    Route::post('/get-home-statistics-for-tarjets-by-status-c', [HomeController::class, 'getHomeStatisticsForTarjetsByStatus']);
    Route::post('/get-home-statistics-for-text-card-in-dashboard-c', [HomeController::class, 'getStatisticsForTextCardInDashBoard']);
    Route::post('/get-stats-client-card-in-dashboard-c', [HomeController::class, 'getStatsCardClientInDashBoard']);
    Route::post('/get-stats-ticket-card-in-dashboard-c', [HomeController::class, 'getStatsCardTicketsInDashBoard']);
    Route::post('/get-stats-finance-card-in-dashboard-c', [HomeController::class, 'getStatsCardFinanceInDashBoard']);
    Route::post('/get-stats-server-card-in-dashboard-c', [HomeController::class, 'getStatsCardServerInDashBoard']);

    Route::group(['prefix' => '/statics'], function () {
        Route::post('/sales-and-prospects/{id}', [StaticsController::class, 'salesAndProspects']);
        Route::post('/sales-and-prospects',      [StaticsController::class, 'salesAndProspects']);
        Route::post('/sales-by-medium/{id}',     [StaticsController::class, 'salesByMedium']);
        Route::post('/sales-by-medium',          [StaticsController::class, 'salesByMedium']);
        Route::post('/compare-sales/{id}',       [StaticsController::class, 'compareSales']);
        Route::post('/compare-sales',            [StaticsController::class, 'compareSales']);
        Route::post('/prospects-by-status/{id}', [StaticsController::class, 'prospectsByStatus']);
        Route::post('/prospects-by-status',      [StaticsController::class, 'prospectsByStatus']);
        Route::post('/ranking-sales',            [StaticsController::class, 'rankingSales']);
        Route::post('/total-prospects',          [StaticsController::class, 'getTotalProspects']);
        Route::post('/total-sales',              [StaticsController::class, 'getTotalSales']);
        Route::post('/total-lost-sales',         [StaticsController::class, 'getLostSales']);
    });
});

Route::get('/home', [HomeController::class, 'index'])->name('home');
