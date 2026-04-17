<?php

use App\Http\Controllers\Module\OLTs\OLTsController;
// use App\Http\Controllers\PaymentTestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/bd-reset', function () {
    \Illuminate\Support\Facades\Artisan::call('migrate:fresh');
    \Illuminate\Support\Facades\Artisan::call('updatecolumndatatablemodule:process');
    \Illuminate\Support\Facades\Artisan::call('updatefielmodule:process');
    return redirect()->back();
});

//Route::get('/payments/{id}', [PaymentTestController::class, 'payments']);
