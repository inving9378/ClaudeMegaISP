<?php

namespace App\Modules\Addons\IA\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\IA\Models\IATarea;
use App\Modules\Addons\IA\Services\ContextoProyectoService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class IATareaController extends Controller
{
    public function __construct(protected ContextoProyectoService $contexto)
    {
    }

    public function index(Request $request)
    {
        $query = IATarea::query();

        if ($estado = $request->input('estado')) {
            $query->where('estado', $estado);
        }
        if ($prio = $request->input('prioridad')) {
            $query->where('prioridad', $prio);
        }
        if ($modulo = $request->input('modulo')) {
            $query->where('modulo_relacionado', $modulo);
        }

        $tareas = $query
            ->orderByRaw("FIELD(estado,'en_progreso','pendiente','completada','cancelada')")
            ->orderByRaw("FIELD(prioridad,'alta','media','baja')")
            ->orderByDesc('id')
            ->get();

        return response()->json(['success' => true, 'data' => $tareas]);
    }

    public function store(Request $request)
    {
        $validator = $this->validar($request);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $tarea = IATarea::create(array_merge($validator->validated(), [
            'created_by' => auth()->id(),
        ]));

        $this->contexto->invalidarCache(auth()->id());
        return response()->json(['success' => true, 'data' => $tarea]);
    }

    public function update(Request $request, $id)
    {
        $tarea = IATarea::findOrFail($id);

        $validator = $this->validar($request, partial: true);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $datos = $validator->validated();
        $datos['updated_by'] = auth()->id();

        if (($datos['estado'] ?? null) === 'completada' && !$tarea->completada_en) {
            $datos['completada_en'] = Carbon::now();
        }
        if (($datos['estado'] ?? null) && $datos['estado'] !== 'completada') {
            $datos['completada_en'] = null;
        }

        $tarea->update($datos);

        $this->contexto->invalidarCache(auth()->id());
        return response()->json(['success' => true, 'data' => $tarea->fresh()]);
    }

    public function destroy($id)
    {
        $tarea = IATarea::findOrFail($id);
        $tarea->delete();
        $this->contexto->invalidarCache(auth()->id());
        return response()->json(['success' => true]);
    }

    public function completar($id)
    {
        $tarea = IATarea::findOrFail($id);
        $tarea->update([
            'estado' => 'completada',
            'completada_en' => Carbon::now(),
            'updated_by' => auth()->id(),
        ]);
        $this->contexto->invalidarCache(auth()->id());
        return response()->json(['success' => true, 'data' => $tarea]);
    }

    protected function validar(Request $request, bool $partial = false): \Illuminate\Validation\Validator
    {
        $required = $partial ? 'sometimes' : 'required';

        return Validator::make($request->all(), [
            'titulo' => [$required, 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'estado' => ['nullable', Rule::in(['pendiente', 'en_progreso', 'completada', 'cancelada'])],
            'prioridad' => ['nullable', Rule::in(['alta', 'media', 'baja'])],
            'modulo_relacionado' => ['nullable', 'string', 'max:120'],
        ]);
    }
}
