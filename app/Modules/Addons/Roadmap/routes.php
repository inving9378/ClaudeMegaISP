<?php

use App\Modules\Addons\Roadmap\Controllers\RoadmapController;
use App\Modules\Addons\Roadmap\Controllers\RoadmapExternalController;
use App\Modules\Addons\Roadmap\Controllers\RoadmapMemoryController;
use Illuminate\Support\Facades\Route;

/*
| ACCESO EXTERNO SIN LOGIN (Circuito de Mejora Continua) — Claude Cowork.
| SIN middleware `web`/`auth` → sin sesión ni cookies. Protegido por token en
| el path + rate limit. Fuera de menús y sitemap.
*/
Route::prefix('api/roadmap-externo')
    ->middleware('throttle:' . config('roadmap_externo.rate_read', 60) . ',1')
    ->group(function () {
        Route::get('/{token}', [RoadmapExternalController::class, 'index']);

        // Variantes PATH-BASED (el fetcher de Cowork descarta el query string).
        //   Detalle:  GET /{token}/item/{id}
        //   Lista:    GET /{token}/q/{estado}/{nivel}/{page}/{perpage}  ("-" = comodín)
        Route::get('/{token}/item/{id}', [RoadmapExternalController::class, 'showItem'])
            ->whereNumber('id');
        Route::get('/{token}/q/{estado}/{nivel}/{page}/{perpage}', [RoadmapExternalController::class, 'queryPath'])
            ->whereNumber('page')->whereNumber('perpage');
    });

Route::prefix('api/roadmap-externo')
    ->middleware('throttle:' . config('roadmap_externo.rate_write', 30) . ',1')
    ->group(function () {
        Route::post('/{token}/item/{id}', [RoadmapExternalController::class, 'updateItem'])
            ->whereNumber('id');

        // Variante de escritura por GET (query params) — el fetcher de Claude Cowork
        // solo hace GET. Misma allowlist de 3 campos, mismas validaciones de enum,
        // mismos guards y mismo log que el POST (delegan en el mismo writeItem()).
        //   GET /{token}/item/{id}/set?estado_aprobacion=..&nivel_riesgo=..&comentarios_claude=..
        Route::get('/{token}/item/{id}/set', [RoadmapExternalController::class, 'setItem'])
            ->whereNumber('id');
    });

Route::middleware(['web', 'auth'])
    ->prefix('api/roadmap')
    ->group(function () {
        Route::get('/items',               [RoadmapController::class, 'index']);
        Route::post('/items',              [RoadmapController::class, 'store']);
        Route::patch('/items/{id}',        [RoadmapController::class, 'update']);
        Route::post('/items/{id}/start',              [RoadmapController::class, 'start']);
        Route::post('/items/{id}/complete',           [RoadmapController::class, 'complete']);
        Route::post('/items/{id}/cancel',             [RoadmapController::class, 'cancel']);
        Route::delete('/items/{id}',                  [RoadmapController::class, 'destroy']);

        // Sub-tareas y bitácora
        Route::patch('/items/{id}/subtasks',                  [RoadmapController::class, 'updateSubtasks']);
        Route::post('/items/{id}/subtasks/{index}/toggle',    [RoadmapController::class, 'toggleSubtask']);
        Route::post('/items/{id}/log',                        [RoadmapController::class, 'addLog']);

        // Memoria CLAUDE.md por item
        Route::get('/items/{id}/memory',          [RoadmapMemoryController::class, 'show']);
        Route::get('/items/{id}/memory/prompt',   [RoadmapMemoryController::class, 'generatePrompt']);
        Route::post('/items/{id}/memory/report',  [RoadmapMemoryController::class, 'appendReport']);
        Route::post('/items/{id}/memory/raw',     [RoadmapMemoryController::class, 'replaceRaw']);
    });
