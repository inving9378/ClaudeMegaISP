<?php

use App\Modules\Core\Documentacion\Controllers\DocumentationContentController;
use App\Modules\Core\Documentacion\Controllers\DocumentationMenuController;
use App\Modules\Core\Documentacion\Controllers\DocumentationSubmenuController;
use Illuminate\Support\Facades\Route;

/*
 * Rutas del módulo core-documentacion.
 *
 * Sistema de documentación interna jerárquica:
 *  Menu -> Submenu -> Content
 *
 * URL prefix `administracion/documentation/*` preservado para compat
 * con frontend. Sub-namespaces internos (DocumentationMenu/Submenu/Content)
 * aplanados a Documentacion/Controllers/ (decisión 2026-05-20).
 *
 * `web` necesario porque loadRoutesFrom no aplica el grupo automáticamente.
 * `check_route_permission` replica el gating legacy.
 */

Route::middleware(['web', 'auth', 'check_route_permission'])
    ->prefix('administracion/documentation')
    ->group(function () {

        Route::prefix('documentation_menu')->group(function () {
            Route::get('/', [DocumentationMenuController::class, 'index']);
            Route::post('/add', [DocumentationMenuController::class, 'store']);
            Route::post('/update/{id}', [DocumentationMenuController::class, 'update']);
            Route::post('/destroy/{id}', [DocumentationMenuController::class, 'destroy']);
            Route::post('/table', [DocumentationMenuController::class, 'table']);
            Route::get('/getById/{id}', [DocumentationMenuController::class, 'getById']);
            Route::get('/get-title/{id}', [DocumentationMenuController::class, 'getTitle']);
            // Ruta para el árbol de documentación (dropdown)
            Route::get('/tree', [DocumentationMenuController::class, 'getTree']);
        });

        Route::prefix('documentation_submenu')->group(function () {
            Route::get('/', [DocumentationSubmenuController::class, 'index']);
            Route::post('/add', [DocumentationSubmenuController::class, 'store']);
            Route::post('/update/{id}', [DocumentationSubmenuController::class, 'update']);
            Route::post('/destroy/{id}', [DocumentationSubmenuController::class, 'destroy']);
            Route::post('/table', [DocumentationSubmenuController::class, 'table']);
            Route::get('/{id}', [DocumentationSubmenuController::class, 'show']);
        });

        Route::prefix('documentation_content')->group(function () {
            Route::post('/add', [DocumentationContentController::class, 'store']);
            Route::post('/update/{id}', [DocumentationContentController::class, 'update']);
            Route::delete('/delete/{id}', [DocumentationContentController::class, 'destroy']);
            Route::get('/{submenuId}/contents', [DocumentationContentController::class, 'getContentsBySubmenuId']);
        });
    });
