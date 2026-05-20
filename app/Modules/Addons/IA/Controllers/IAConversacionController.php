<?php

namespace App\Modules\Addons\IA\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\IA\Models\IAConversacion;
use App\Modules\Addons\IA\Models\IAProveedor;
use App\Modules\Addons\IA\Models\IAProyecto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class IAConversacionController extends Controller
{
    public function index(Request $request)
    {
        $query = IAConversacion::query()
            ->with(['proveedor:id,nombre,driver'])
            ->where('user_id', auth()->id());

        if ($proyectoId = $request->input('ia_proyecto_id')) {
            $query->where('ia_proyecto_id', $proyectoId);
        }

        $conversaciones = $query
            ->orderByDesc('ultimo_mensaje_at')
            ->orderByDesc('id')
            ->get();

        return response()->json(['success' => true, 'data' => $conversaciones]);
    }

    public function show($id)
    {
        $conversacion = IAConversacion::with(['mensajes.archivos', 'proveedor', 'proyecto'])
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $conversacion]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'titulo' => ['nullable', 'string', 'max:255'],
            'ia_proyecto_id' => ['nullable', 'integer', 'exists:ia_proyectos,id'],
            'ia_proveedor_id' => ['required', 'integer', 'exists:ia_proveedores,id'],
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $proveedor = IAProveedor::findOrFail($request->input('ia_proveedor_id'));

        $proyectoId = $request->input('ia_proyecto_id');
        if (!$proyectoId) {
            $default = IAProyecto::where('es_default', true)->first();
            $proyectoId = $default?->id;
        }

        $conv = IAConversacion::create([
            'titulo' => $request->input('titulo') ?: 'Nuevo chat',
            'ia_proyecto_id' => $proyectoId,
            'ia_proveedor_id' => $proveedor->id,
            'modelo' => $proveedor->modelo_default,
            'user_id' => auth()->id(),
            'created_by' => auth()->id(),
        ]);

        return response()->json(['success' => true, 'data' => $conv]);
    }

    public function update(Request $request, $id)
    {
        $conv = IAConversacion::where('user_id', auth()->id())->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'titulo' => ['nullable', 'string', 'max:255'],
            'ia_proyecto_id' => ['nullable', 'integer', 'exists:ia_proyectos,id'],
            'ia_proveedor_id' => ['nullable', 'integer', 'exists:ia_proveedores,id'],
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $conv->update(array_filter([
            'titulo' => $request->input('titulo'),
            'ia_proyecto_id' => $request->input('ia_proyecto_id'),
            'ia_proveedor_id' => $request->input('ia_proveedor_id'),
            'updated_by' => auth()->id(),
        ], fn ($v) => $v !== null));

        return response()->json(['success' => true, 'data' => $conv->fresh()]);
    }

    public function destroy($id)
    {
        $conv = IAConversacion::where('user_id', auth()->id())->findOrFail($id);
        $conv->delete();
        return response()->json(['success' => true]);
    }
}
