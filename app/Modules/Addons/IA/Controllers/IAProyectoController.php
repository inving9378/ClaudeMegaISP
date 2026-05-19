<?php

namespace App\Modules\Addons\IA\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\IA\Models\IAProyecto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class IAProyectoController extends Controller
{
    public function index()
    {
        $proyectos = IAProyecto::query()
            ->where(fn ($q) => $q->where('user_id', auth()->id())->orWhere('es_default', true))
            ->withCount('conversaciones')
            ->orderByDesc('es_default')
            ->orderBy('nombre')
            ->get();

        return response()->json(['success' => true, 'data' => $proyectos]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => ['required', 'string', 'max:120'],
            'descripcion' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $proyecto = IAProyecto::create([
            'nombre' => $request->input('nombre'),
            'descripcion' => $request->input('descripcion'),
            'color' => $request->input('color'),
            'user_id' => auth()->id(),
            'created_by' => auth()->id(),
        ]);

        return response()->json(['success' => true, 'data' => $proyecto]);
    }

    public function update(Request $request, $id)
    {
        $proyecto = IAProyecto::findOrFail($id);

        if ($proyecto->es_default) {
            return response()->json([
                'success' => false,
                'message' => 'El proyecto por defecto no puede editarse.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'nombre' => ['required', 'string', 'max:120'],
            'descripcion' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $proyecto->update([
            'nombre' => $request->input('nombre'),
            'descripcion' => $request->input('descripcion'),
            'color' => $request->input('color'),
            'updated_by' => auth()->id(),
        ]);

        return response()->json(['success' => true, 'data' => $proyecto]);
    }

    public function destroy($id)
    {
        $proyecto = IAProyecto::findOrFail($id);

        if ($proyecto->es_default) {
            return response()->json([
                'success' => false,
                'message' => 'El proyecto por defecto no puede eliminarse.',
            ], 422);
        }

        // Las conversaciones quedan huérfanas (ia_proyecto_id = null) por la FK con nullOnDelete.
        $proyecto->delete();
        return response()->json(['success' => true]);
    }
}
