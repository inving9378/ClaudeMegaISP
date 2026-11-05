<?php

namespace App\Modules\Addons\Roadmap\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Roadmap\Models\RoadmapModuloFuncionamiento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * #1145 — «Cómo debe funcionar» por módulo. Lectura con `roadmap_view`, escritura con
 * `roadmap_manage` (permisos existentes, ninguno nuevo). Cada guardado agrega una versión.
 */
class ModuloFuncionamientoController extends Controller
{
    // GET /api/roadmap/torre/modulos/funcionamiento?modulo=
    public function mostrar(Request $request): JsonResponse
    {
        $this->authorize('roadmap_view');
        $data = $request->validate(['modulo' => ['required', 'string', 'max:190']]);

        $versiones = RoadmapModuloFuncionamiento::where('modulo', $data['modulo'])
            ->orderByDesc('id')->limit(50)->get(['id', 'texto', 'autor_nombre', 'created_at']);

        return response()->json([
            'ok'        => true,
            'modulo'    => $data['modulo'],
            'vigente'   => $versiones->first(),
            'historial' => $versiones->values(),
            'puede'     => $request->user()->can('roadmap_manage'),
        ]);
    }

    // POST /api/roadmap/torre/modulos/funcionamiento {modulo, texto}
    public function guardar(Request $request): JsonResponse
    {
        $this->authorize('roadmap_manage');
        $data = $request->validate([
            'modulo' => ['required', 'string', 'max:190'],
            'texto'  => ['required', 'string', 'max:20000'],
        ]);

        $u = $request->user();
        $v = RoadmapModuloFuncionamiento::create([
            'modulo'       => $data['modulo'],
            'texto'        => $data['texto'],
            'autor_id'     => $u->id,
            'autor_nombre' => trim(($u->name ?? '') . ' ' . ($u->father_last_name ?? '')) ?: ($u->login_user ?? null),
        ]);

        return response()->json(['ok' => true, 'version' => $v->only(['id', 'texto', 'autor_nombre', 'created_at'])]);
    }
}
