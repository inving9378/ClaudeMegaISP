<?php

namespace App\Modules\Addons\IA\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\IA\Models\IAMemoriaProyecto;
use App\Modules\Addons\IA\Services\MemoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class IAMemoriaController extends Controller
{
    public function __construct(protected MemoriaService $memoria) {}

    public function index(Request $request)
    {
        $q = IAMemoriaProyecto::query();

        if ($request->boolean('solo_vigentes', true)) {
            $q->vigentes();
        }
        if ($tipo = $request->input('tipo')) {
            $q->where('tipo', $tipo);
        }
        if ($modulo = $request->input('modulo')) {
            $q->where('modulo_relacionado', $modulo);
        }

        $items = $q->orderByDesc('relevancia')
            ->orderByDesc('updated_at')
            ->limit(500)
            ->get();

        return response()->json(['success' => true, 'data' => $items]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tipo' => ['required', 'in:' . implode(',', IAMemoriaProyecto::TIPOS)],
            'contenido' => ['required', 'string', 'max:500'],
            'modulo_relacionado' => ['nullable', 'string', 'max:100'],
            'relevancia' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $m = IAMemoriaProyecto::create([
            'tipo' => $request->input('tipo'),
            'contenido' => $request->input('contenido'),
            'modulo_relacionado' => $request->input('modulo_relacionado'),
            'relevancia' => $request->input('relevancia', 5),
        ]);

        return response()->json(['success' => true, 'data' => $m]);
    }

    public function update(Request $request, $id)
    {
        $m = IAMemoriaProyecto::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'tipo' => ['sometimes', 'in:' . implode(',', IAMemoriaProyecto::TIPOS)],
            'contenido' => ['sometimes', 'string', 'max:500'],
            'modulo_relacionado' => ['sometimes', 'nullable', 'string', 'max:100'],
            'relevancia' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'obsoleto' => ['sometimes', 'boolean'],
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $m->update($request->only(['tipo', 'contenido', 'modulo_relacionado', 'relevancia', 'obsoleto']));

        return response()->json(['success' => true, 'data' => $m->fresh()]);
    }

    public function destroy($id)
    {
        IAMemoriaProyecto::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }

    /**
     * Dispara la limpieza por antigüedad (default 90 días).
     */
    public function limpiarAntiguos(Request $request)
    {
        $dias = max(1, (int) $request->input('dias', 90));
        $eliminados = $this->memoria->limpiarAntiguos($dias);
        return response()->json([
            'success' => true,
            'eliminados' => $eliminados,
            'dias' => $dias,
        ]);
    }

    /**
     * Dispara la detección de contradicciones vía IA.
     */
    public function limpiarObsoletos()
    {
        $marcados = $this->memoria->limpiarObsoletos();
        return response()->json([
            'success' => true,
            'marcados_obsoletos' => $marcados,
        ]);
    }
}
