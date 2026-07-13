<?php

use App\Modules\Addons\Roadmap\Controllers\RoadmapController;
use App\Modules\Addons\Roadmap\Controllers\RoadmapExternalController;
use App\Modules\Addons\Roadmap\Controllers\RoadmapMcpController;
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

        // Variante de ESCRITURA por PATH (el fetcher de Cowork descarta el query string).
        //   GET /{token}/item/{id}/set/{estado}/{nivel}/{comentario?}   ("-" = no cambiar)
        // `comentario` (opcional) URL-encoded; ->where('.+') admite el texto codificado.
        Route::get('/{token}/item/{id}/set/{estado}/{nivel}/{comentario?}', [RoadmapExternalController::class, 'setItemPath'])
            ->whereNumber('id')
            ->where('comentario', '.+');

        // Variante de ESCRITURA por PATH con comentario en BASE64URL (ver #317): evita el
        // 403 de nginx cuando el comentario trae '/' (nginx bloquea %2F antes de Laravel).
        //   GET /{token}/item/{id}/setb64/{estado}/{nivel}/{comentario_b64?}
        Route::get('/{token}/item/{id}/setb64/{estado}/{nivel}/{comentarioB64?}', [RoadmapExternalController::class, 'setItemPathB64'])
            ->whereNumber('id')
            ->where('comentarioB64', '[A-Za-z0-9_-]+');
    });

/*
| CONECTOR MCP de la Hoja de Ruta (Circuito CC) — Streamable HTTP / JSON-RPC 2.0.
| Custom connector de claude.ai para Cowork (sin allowlist de red). SIN middleware
| `web`/`auth` → sin sesión ni CSRF. Protegido por secreto en el path + rate limit.
| Endpoint: POST https://dev.meganett.com.mx/mcp/{secret}  (GET → 405).
*/
Route::prefix('mcp')
    ->middleware('throttle:' . config('mcp_roadmap.rate', 120) . ',1')
    ->group(function () {
        Route::post('/{secret}', [RoadmapMcpController::class, 'handle']);
        Route::get('/{secret}', [RoadmapMcpController::class, 'methodNotAllowed']);
    });

Route::middleware(['web', 'auth'])
    ->prefix('api/roadmap')
    ->group(function () {
        // Torre de control del Circuito (dashboard en vivo) + kill switch.
        Route::get('/torre',               [RoadmapController::class, 'torre']);
        // Estado en vivo ligero para el polling de la Torre (#335).
        Route::get('/circuito/estado',     [RoadmapController::class, 'estado']);
        // Árbol de sesiones `claude` vivas en el box + banner de colisión (#345). Solo backend
        // por ahora (endpoint de lectura); panel Vue de la Torre queda para una siguiente entrega.
        Route::get('/circuito/sesiones',   [RoadmapController::class, 'sesiones']);
        // Disparo manual de una vuelta + marcar item urgente (#337).
        Route::post('/circuito/disparar',  [RoadmapController::class, 'disparar']);
        Route::post('/items/{id}/urgente', [RoadmapController::class, 'urgente'])->whereNumber('id');
        Route::post('/circuito/toggle',    [RoadmapController::class, 'toggleCircuito']);
        Route::post('/circuito/decidir',   [RoadmapController::class, 'decidir']);
        Route::post('/circuito/elegir-opcion', [RoadmapController::class, 'elegirOpcion']);
        Route::post('/circuito/seguimiento', [RoadmapController::class, 'seguimiento']);
        // Vista de Integración / Ramas (#315)
        Route::get('/integracion',            [RoadmapController::class, 'integracion']);
        Route::post('/integracion/merge',     [RoadmapController::class, 'integracionMerge']);
        Route::post('/integracion/rechazar',  [RoadmapController::class, 'integracionRechazar']);
        Route::post('/integracion/revert',    [RoadmapController::class, 'integracionRevert']);
        Route::post('/integracion/modo',          [RoadmapController::class, 'integracionModo']);
        Route::post('/integracion/marcar-version', [RoadmapController::class, 'integracionMarcarVersion']);
        // Voz (es-*) para 🔊 Escuchar en la Torre (#424).
        Route::post('/integracion/voz',            [RoadmapController::class, 'integracionVoz']);
        // Ciclo de vida / archivo (#334): historial + archivar (individual/masivo) + desarchivar ("quiero verlo")
        Route::post('/circuito/worker-nombre',      [RoadmapController::class, 'workerNombre']);
        Route::get('/integracion/historial',       [RoadmapController::class, 'integracionHistorial']);
        Route::post('/integracion/archivar',       [RoadmapController::class, 'integracionArchivar']);
        Route::post('/integracion/desarchivar',    [RoadmapController::class, 'integracionDesarchivar']);

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
