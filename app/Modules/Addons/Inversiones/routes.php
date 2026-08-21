<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'check_route_permission'])
    ->prefix('inversiones')
    ->group(function () {
        Route::get('/', fn () => view('addon-inversiones::dashboard'));
    });
