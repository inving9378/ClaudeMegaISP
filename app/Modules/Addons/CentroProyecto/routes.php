<?php

use App\Modules\Addons\CentroProyecto\Controllers\CentroProyectoController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('centro-proyecto')->group(function () {
    Route::get('/', [CentroProyectoController::class, 'index'])->name('centro-proyecto.index');
});
