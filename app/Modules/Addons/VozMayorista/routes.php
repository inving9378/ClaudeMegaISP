<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Voz Mayorista — rutas
|--------------------------------------------------------------------------
|
| TODAS pasan por `rol.instancia:operador`: este módulo administra el negocio
| mayorista de Meganet (tarifas de carrier, márgenes, inventario de DID) y no
| tiene nada que hacer en la instalación de un cliente arrendado.
|
| El grupo lleva `web` explícito porque `loadRoutesFrom()` no lo aplica solo.
|
| PREFIJO `/voz-mayorista`, no `/voz`: el módulo Planes (addon-planes, activo) ya
| usa `/voz` para los planes de servicio de voz que se venden al cliente final
| (`voz/crear`, `voz/table`, modelo Voise). Son dominios distintos —aquel vende un
| plan a un suscriptor, este administra el negocio mayorista— y compartir raíz solo
| serviría para confundir un route:list. Los permisos `voz.` y las tablas `voz_` sí
| se quedan: ahí no hay colisión (Planes usa `plan_*_voz` y `client_service_voz`).
|
| En el Bloque 1 solo existe el panel. Los demás endpoints llegan en el bloque
| de API, una vez aprobado el modelo de datos.
|
*/

Route::middleware(['web', 'auth', 'rol.instancia:operador'])
    ->prefix('voz-mayorista')
    ->name('voz.')
    ->group(function () {

        Route::get('/', [\App\Modules\Addons\VozMayorista\Controllers\PanelController::class, 'index'])
            ->name('panel')
            ->middleware('can:voz.panel.view');
    });
