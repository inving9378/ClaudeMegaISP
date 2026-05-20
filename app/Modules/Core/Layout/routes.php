<?php

use App\Modules\Core\Layout\Controllers\ConfigAppLayoutController;
use Illuminate\Support\Facades\Route;

/*
 * Rutas del módulo Core/Layout.
 * Mantienen el middleware `auth` del bloque global donde estaban originalmente
 * en routes/web.php (no estaban dentro de check_route_permission).
 *
 * Se añade `web` antes de `auth` porque BaseModuleServiceProvider carga este
 * archivo con loadRoutesFrom(), que NO aplica el grupo `web` automáticamente.
 * Sin él no corre StartSession y `auth` no podría leer la sesión del usuario.
 */

Route::middleware(['web', 'auth'])->group(function () {
    Route::post('/save-app-config-layout', [ConfigAppLayoutController::class, 'saveAppConfigLayout']);
    Route::post('/get-config-tabs', [ConfigAppLayoutController::class, 'getConfigTabs']);
    Route::post('/set-config-tabs', [ConfigAppLayoutController::class, 'setConfigTabs']);
});
