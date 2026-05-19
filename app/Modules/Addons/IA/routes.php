<?php

use App\Modules\Addons\IA\Controllers\IAChatController;
use App\Modules\Addons\IA\Controllers\IAConfiguracionController;
use App\Modules\Addons\IA\Controllers\IAConversacionController;
use App\Modules\Addons\IA\Controllers\IAHistorialController;
use App\Modules\Addons\IA\Controllers\IANotaController;
use App\Modules\Addons\IA\Controllers\IAPromptsController;
use App\Modules\Addons\IA\Controllers\IAPromptUsuarioController;
use App\Modules\Addons\IA\Controllers\IAProveedorController;
use App\Modules\Addons\IA\Controllers\IAProyectoController;
use App\Modules\Addons\IA\Controllers\IASesionController;
use App\Modules\Addons\IA\Controllers\IATareaController;
use Illuminate\Support\Facades\Route;

/*
 * Rutas del módulo addon-ia.
 *
 * Asistente IA multi-proveedor (Claude, OpenAI, Gemini) con historial,
 * prompts guardados, proyectos, tareas, notas, sesiones de trabajo y
 * dashboard de uso de tokens.
 *
 * `web` necesario porque loadRoutesFrom no aplica el grupo automáticamente.
 * Gating con `permission:` (spatie/laravel-permission).
 */

Route::middleware(['web', 'auth'])->prefix('ia')->group(function () {

    // Chat principal, historial, prompts (vista)
    Route::middleware('permission:ia_view_chat')->group(function () {
        Route::get('/', [IAChatController::class, 'index'])->name('ia.index');
        Route::get('/historial', [IAHistorialController::class, 'index'])->name('ia.historial');
        Route::get('/historial/tabla', [IAHistorialController::class, 'tabla']);
    });

    Route::middleware('permission:ia_add_chat')->group(function () {
        Route::post('/conversaciones/{id}/enviar', [IAChatController::class, 'enviar']);
    });

    // Prompts: vista pública (Mis Prompts)
    Route::middleware('permission:ia_view_prompts')->group(function () {
        Route::get('/prompts', [IAPromptsController::class, 'index'])->name('ia.prompts');
    });

    // Configuración + proveedores (solo admin)
    Route::middleware('permission:ia_manage_proveedores')->group(function () {
        Route::get('/configuracion', [IAConfiguracionController::class, 'index'])->name('ia.configuracion');
        Route::get('/configuracion/uso-tokens', [IAConfiguracionController::class, 'usoTokens']);

        Route::prefix('proveedores')->group(function () {
            Route::get('/', [IAProveedorController::class, 'index']);
            Route::post('/store', [IAProveedorController::class, 'store']);
            Route::post('/update/{id}', [IAProveedorController::class, 'update']);
            Route::delete('/destroy/{id}', [IAProveedorController::class, 'destroy']);
            Route::post('/probar/{id}', [IAProveedorController::class, 'probar']);
            Route::post('/toggle-activo/{id}', [IAProveedorController::class, 'toggleActivo']);
        });
    });

    // Proyectos
    Route::middleware('permission:ia_manage_proyectos')->prefix('proyectos')->group(function () {
        Route::get('/', [IAProyectoController::class, 'index']);
        Route::post('/store', [IAProyectoController::class, 'store']);
        Route::post('/update/{id}', [IAProyectoController::class, 'update']);
        Route::delete('/destroy/{id}', [IAProyectoController::class, 'destroy']);
    });

    // Conversaciones — split por permission
    Route::prefix('conversaciones')->group(function () {
        Route::middleware('permission:ia_view_chat')->group(function () {
            Route::get('/', [IAConversacionController::class, 'index']);
            Route::get('/{id}', [IAConversacionController::class, 'show']);
        });
        Route::middleware('permission:ia_add_chat')->group(function () {
            Route::post('/store', [IAConversacionController::class, 'store']);
        });
        Route::middleware('permission:ia_edit_chat')->group(function () {
            Route::post('/update/{id}', [IAConversacionController::class, 'update']);
        });
        Route::middleware('permission:ia_delete_chat')->group(function () {
            Route::delete('/destroy/{id}', [IAConversacionController::class, 'destroy']);
        });
    });

    // Tareas
    Route::middleware('permission:ia_manage_tareas')->prefix('tareas')->group(function () {
        Route::get('/', [IATareaController::class, 'index']);
        Route::post('/store', [IATareaController::class, 'store']);
        Route::post('/update/{id}', [IATareaController::class, 'update']);
        Route::post('/completar/{id}', [IATareaController::class, 'completar']);
        Route::delete('/destroy/{id}', [IATareaController::class, 'destroy']);
    });

    // Notas
    Route::middleware('permission:ia_manage_notas')->prefix('notas')->group(function () {
        Route::get('/', [IANotaController::class, 'index']);
        Route::post('/store', [IANotaController::class, 'store']);
        Route::post('/update/{id}', [IANotaController::class, 'update']);
        Route::delete('/destroy/{id}', [IANotaController::class, 'destroy']);
    });

    // Sesiones de trabajo
    Route::middleware('permission:ia_manage_sesiones')->prefix('sesiones')->group(function () {
        Route::get('/', [IASesionController::class, 'index']);
        Route::post('/abrir', [IASesionController::class, 'abrir']);
        Route::post('/cerrar/{id}', [IASesionController::class, 'cerrar']);
    });

    // Prompts CRUD
    Route::prefix('prompts')->group(function () {
        Route::middleware('permission:ia_view_prompts')->group(function () {
            Route::get('/listar', [IAPromptUsuarioController::class, 'listar']);
            Route::post('/usar/{id}', [IAPromptUsuarioController::class, 'usar']);
        });
        Route::middleware('permission:ia_manage_prompts')->group(function () {
            Route::post('/store', [IAPromptUsuarioController::class, 'store']);
            Route::post('/update/{id}', [IAPromptUsuarioController::class, 'update']);
            Route::delete('/destroy/{id}', [IAPromptUsuarioController::class, 'destroy']);
        });
    });

});
