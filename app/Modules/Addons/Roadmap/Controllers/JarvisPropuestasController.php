<?php

namespace App\Modules\Addons\Roadmap\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Roadmap\Models\JarvisPropuesta;
use App\Modules\Addons\Roadmap\Services\JarvisPropuestas\PropuestaException;
use App\Modules\Addons\Roadmap\Services\JarvisPropuestas\PropuestaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * #1110 — Fase 2 de #1068: aquí (y solo aquí) se crean los items que Jarvis propuso en el chat.
 * Cada ruta exige el permiso propio `roadmap.jarvis.crear_items`, que la propuesta sea del usuario y
 * que la huella coincida con la última versión guardada en el servidor.
 */
class JarvisPropuestasController extends Controller
{
    public function __construct(private PropuestaService $svc)
    {
    }

    public function editar(Request $request, int $id): JsonResponse
    {
        return $this->correr(function () use ($request, $id) {
            $d = $request->validate(['huella' => ['required', 'string'], 'items' => ['required', 'array']]);
            $p = $this->svc->editar($this->propia($id), $d['huella'], $d['items']);

            return ['ok' => true, 'propuesta' => $this->svc->presentar($p)];
        });
    }

    public function crear(Request $request, int $id): JsonResponse
    {
        return $this->correr(function () use ($request, $id) {
            $d = $request->validate(['huella' => ['required', 'string'], 'al_inicio' => ['sometimes', 'boolean'],
                'confirmados_c' => ['sometimes', 'array'], 'confirmados_c.*' => ['integer']]);

            return $this->svc->crear($this->propia($id), $request->user(), $d['huella'], (bool) ($d['al_inicio'] ?? false), $d['confirmados_c'] ?? []);
        });
    }

    public function descartar(Request $request, int $id): JsonResponse
    {
        return $this->correr(function () use ($request, $id) {
            $d = $request->validate(['huella' => ['required', 'string']]);
            $this->svc->descartar($this->propia($id), $d['huella']);

            return ['ok' => true];
        });
    }

    public function deshacer(Request $request, int $itemId): JsonResponse
    {
        return $this->correr(fn () => ['ok' => true, 'mensaje' => $this->svc->deshacer($itemId, $request->user())]);
    }

    private function propia(int $id): JarvisPropuesta
    {
        $p = JarvisPropuesta::where('id', $id)->where('user_id', auth()->id())->first();
        if (! $p) {
            throw new PropuestaException('no_encontrada', 'No encuentro esa propuesta.', 404);
        }

        return $p;
    }

    private function correr(callable $f): JsonResponse
    {
        if (! auth()->user()?->can(PropuestaService::PERMISO)) {
            return response()->json(['ok' => false, 'code' => 'sin_permiso', 'mensaje' => 'No tienes permiso para crear items desde el chat.'], 403);
        }
        try {
            return response()->json($f());
        } catch (PropuestaException $e) {
            return response()->json(['ok' => false, 'code' => $e->codigo, 'mensaje' => $e->getMessage()], $e->http);
        }
    }
}
