<?php

namespace App\Modules\Addons\IA\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\IA\Models\IANotaProyecto;
use App\Modules\Addons\IA\Services\ContextoProyectoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class IANotaController extends Controller
{
    public function __construct(protected ContextoProyectoService $contexto)
    {
    }

    public function index(Request $request)
    {
        $query = IANotaProyecto::query();
        if ($request->boolean('solo_importantes')) {
            $query->where('importante', true);
        }
        if ($cat = $request->input('categoria')) {
            $query->where('categoria', $cat);
        }
        $notas = $query->orderByDesc('importante')->orderByDesc('id')->get();
        return response()->json(['success' => true, 'data' => $notas]);
    }

    public function store(Request $request)
    {
        $validator = $this->validar($request);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        $nota = IANotaProyecto::create(array_merge($validator->validated(), [
            'created_by' => auth()->id(),
        ]));
        $this->contexto->invalidarCache(auth()->id());
        return response()->json(['success' => true, 'data' => $nota]);
    }

    public function update(Request $request, $id)
    {
        $nota = IANotaProyecto::findOrFail($id);
        $validator = $this->validar($request, partial: true);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        $datos = $validator->validated();
        $datos['updated_by'] = auth()->id();
        $nota->update($datos);
        $this->contexto->invalidarCache(auth()->id());
        return response()->json(['success' => true, 'data' => $nota->fresh()]);
    }

    public function destroy($id)
    {
        $nota = IANotaProyecto::findOrFail($id);
        $nota->delete();
        $this->contexto->invalidarCache(auth()->id());
        return response()->json(['success' => true]);
    }

    protected function validar(Request $request, bool $partial = false): \Illuminate\Validation\Validator
    {
        $required = $partial ? 'sometimes' : 'required';
        return Validator::make($request->all(), [
            'titulo' => [$required, 'string', 'max:255'],
            'contenido' => [$required, 'string'],
            'categoria' => ['nullable', 'string', 'max:120'],
            'importante' => ['boolean'],
        ]);
    }
}
