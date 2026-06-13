<?php

use App\Modules\Addons\VoIP\Controllers\ExtensionController;
use App\Modules\Addons\VoIP\Controllers\GrupoTimbradoController;
use App\Modules\Addons\VoIP\Controllers\IaBotController;
use App\Modules\Addons\VoIP\Controllers\TroncalController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('voip')->group(function () {

    // ── Vista principal ───────────────────────────────────────────────────────
    Route::get('/', [TroncalController::class, 'index'])->name('voip.index');

    // ════════════════════════════════════════════════════════════════════════
    // TRONCALES
    // ════════════════════════════════════════════════════════════════════════
    Route::get('/troncales',                          [TroncalController::class, 'index'])->name('voip.troncales.index');
    Route::get('/troncales/data',                     [TroncalController::class, 'data'])->name('voip.troncales.data');
    Route::post('/troncales',                         [TroncalController::class, 'store'])->name('voip.troncales.store');
    Route::put('/troncales/{troncal}',                [TroncalController::class, 'update'])->name('voip.troncales.update');
    Route::delete('/troncales/{troncal}',             [TroncalController::class, 'destroy'])->name('voip.troncales.destroy');
    Route::post('/troncales/{troncal}/provisionar',   [TroncalController::class, 'provisionar'])->name('voip.troncales.provisionar');
    Route::post('/troncales/{troncal}/desprovisionar',[TroncalController::class, 'desprovisionar'])->name('voip.troncales.desprovisionar');
    Route::get('/probar-conexion',                    [TroncalController::class, 'probarConexion'])->name('voip.probar-conexion');
    Route::get('/troncales/{troncal}/verificar',      [TroncalController::class, 'verificarTroncal'])->name('voip.troncales.verificar');

    // ════════════════════════════════════════════════════════════════════════
    // EXTENSIONES
    // ════════════════════════════════════════════════════════════════════════
    Route::get('/extensiones',                             [ExtensionController::class, 'index'])->name('voip.extensiones.index');
    Route::get('/extensiones/data',                        [ExtensionController::class, 'data'])->name('voip.extensiones.data');
    Route::get('/extensiones/usuarios',                    [ExtensionController::class, 'usuarios'])->name('voip.extensiones.usuarios');
    Route::post('/extensiones',                            [ExtensionController::class, 'store'])->name('voip.extensiones.store');
    Route::put('/extensiones/{extension}',                 [ExtensionController::class, 'update'])->name('voip.extensiones.update');
    Route::delete('/extensiones/{extension}',              [ExtensionController::class, 'destroy'])->name('voip.extensiones.destroy');
    Route::post('/extensiones/{extension}/provisionar',    [ExtensionController::class, 'provisionar'])->name('voip.extensiones.provisionar');
    Route::post('/extensiones/{extension}/desprovisionar', [ExtensionController::class, 'desprovisionar'])->name('voip.extensiones.desprovisionar');
    Route::patch('/extensiones/{extension}/toggle',          [ExtensionController::class, 'toggle'])->name('voip.extensiones.toggle');
    Route::get('/extensiones/{extension}/verificar',         [ExtensionController::class, 'verificar'])->name('voip.extensiones.verificar');
    Route::get('/extensiones/estados',                       [ExtensionController::class, 'estados'])->name('voip.extensiones.estados');

    // ════════════════════════════════════════════════════════════════════════
    // GRUPOS DE TIMBRADO
    // ════════════════════════════════════════════════════════════════════════
    Route::get('/grupos-timbrado',                          [GrupoTimbradoController::class, 'index'])->name('voip.grupos.index');
    Route::get('/grupos-timbrado/data',                     [GrupoTimbradoController::class, 'data'])->name('voip.grupos.data');
    Route::get('/grupos-timbrado/extensiones-disponibles',  [GrupoTimbradoController::class, 'extensionesDisponibles'])->name('voip.grupos.extensiones');
    Route::post('/grupos-timbrado',                         [GrupoTimbradoController::class, 'store'])->name('voip.grupos.store');
    Route::put('/grupos-timbrado/{grupoTimbrado}',          [GrupoTimbradoController::class, 'update'])->name('voip.grupos.update');
    Route::delete('/grupos-timbrado/{grupoTimbrado}',       [GrupoTimbradoController::class, 'destroy'])->name('voip.grupos.destroy');

    // ════════════════════════════════════════════════════════════════════════
    // IA BOT (Fase D2)
    // ════════════════════════════════════════════════════════════════════════
    Route::get('/ia-bot',                               [IaBotController::class, 'index'])->name('voip.ia-bot.index');

    // API
    Route::get('/ia-bot/config',                        [IaBotController::class, 'getConfig'])->name('voip.ia-bot.config.get');
    Route::put('/ia-bot/config',                        [IaBotController::class, 'saveConfig'])->name('voip.ia-bot.config.save');
    Route::get('/ia-bot/conversaciones',                [IaBotController::class, 'conversaciones'])->name('voip.ia-bot.conversaciones');
    Route::get('/ia-bot/leads',                         [IaBotController::class, 'leads'])->name('voip.ia-bot.leads');
    Route::put('/ia-bot/leads/{lead}',                  [IaBotController::class, 'updateLead'])->name('voip.ia-bot.leads.update');
    Route::get('/ia-bot/kb',                            [IaBotController::class, 'kbIndex'])->name('voip.ia-bot.kb.index');
    Route::post('/ia-bot/kb',                           [IaBotController::class, 'kbStore'])->name('voip.ia-bot.kb.store');
    Route::put('/ia-bot/kb/{kb}',                       [IaBotController::class, 'kbUpdate'])->name('voip.ia-bot.kb.update');
    Route::delete('/ia-bot/kb/{kb}',                    [IaBotController::class, 'kbDestroy'])->name('voip.ia-bot.kb.destroy');
});
