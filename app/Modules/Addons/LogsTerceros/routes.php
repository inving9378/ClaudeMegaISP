<?php

use App\Modules\Addons\LogsTerceros\Controllers\LogsTercerosController as C;
use Illuminate\Support\Facades\Route;

/*
 * Logs de terceros (Administración): ver, completos y sin editar, archivos de log del servidor
 * agrupados. Ver ahí: logs_terceros.ver. Dar de alta/cambiar/quitar grupos y rutas:
 * logs_terceros.gestionar. Los dos los reciben solo super-administrator y DESARROLLADOR (no
 * terminan en .view, así que no se reparten a los demás roles). `web` explícito porque
 * loadRoutesFrom() no lo aplica solo.
 */
Route::middleware(['web', 'auth', 'permission:logs_terceros.ver'])
    ->prefix('admin/logs-terceros')
    ->group(function () {
        Route::get('/', [C::class, 'index'])->name('logs-terceros.index');
        Route::get('/datos', [C::class, 'datos'])->name('logs-terceros.datos');
        Route::get('/archivos/{archivo}/contenido', [C::class, 'contenido'])->whereNumber('archivo')->name('logs-terceros.contenido');
        Route::get('/archivos/{archivo}/descargar', [C::class, 'descargar'])->whereNumber('archivo')->name('logs-terceros.descargar');
    });

Route::middleware(['web', 'auth', 'permission:logs_terceros.gestionar'])
    ->prefix('admin/logs-terceros')
    ->group(function () {
        Route::post('/grupos', [C::class, 'guardarGrupo'])->name('logs-terceros.grupos.store');
        Route::put('/grupos/{grupo}', [C::class, 'guardarGrupo'])->whereNumber('grupo')->name('logs-terceros.grupos.update');
        Route::delete('/grupos/{grupo}', [C::class, 'borrarGrupo'])->whereNumber('grupo')->name('logs-terceros.grupos.destroy');
        Route::post('/archivos', [C::class, 'guardarArchivo'])->name('logs-terceros.archivos.store');
        Route::put('/archivos/{archivo}', [C::class, 'guardarArchivo'])->whereNumber('archivo')->name('logs-terceros.archivos.update');
        Route::delete('/archivos/{archivo}', [C::class, 'borrarArchivo'])->whereNumber('archivo')->name('logs-terceros.archivos.destroy');
    });
