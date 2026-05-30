<?php

use App\Modules\Addons\Demo\Controllers\DemoController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'check_route_permission'])
    ->prefix('api/demo')
    ->group(function () {
        Route::get('/items', [DemoController::class, 'index'])->name('api.demo.items');
    });
