<?php

use App\Modules\Addons\VoIP\Controllers\TroncalController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('voip')->group(function () {

    // ── Vista principal ───────────────────────────────────────────────────────
    Route::get('/', [TroncalController::class, 'index'])->name('voip.index');
    Route::get('/troncales', [TroncalController::class, 'index'])->name('voip.troncales.index');

    // ── API JSON (data, CRUD) ─────────────────────────────────────────────────
    Route::get('/troncales/data',                   [TroncalController::class, 'data'])->name('voip.troncales.data');
    Route::post('/troncales',                        [TroncalController::class, 'store'])->name('voip.troncales.store');
    Route::put('/troncales/{troncal}',               [TroncalController::class, 'update'])->name('voip.troncales.update');
    Route::delete('/troncales/{troncal}',            [TroncalController::class, 'destroy'])->name('voip.troncales.destroy');

    // ── Provisión ─────────────────────────────────────────────────────────────
    Route::post('/troncales/{troncal}/provisionar',   [TroncalController::class, 'provisionar'])->name('voip.troncales.provisionar');
    Route::post('/troncales/{troncal}/desprovisionar',[TroncalController::class, 'desprovisionar'])->name('voip.troncales.desprovisionar');

    // ── Tests de conectividad ─────────────────────────────────────────────────
    Route::get('/probar-conexion',                   [TroncalController::class, 'probarConexion'])->name('voip.probar-conexion');
    Route::get('/troncales/{troncal}/verificar',     [TroncalController::class, 'verificarTroncal'])->name('voip.troncales.verificar');
});
