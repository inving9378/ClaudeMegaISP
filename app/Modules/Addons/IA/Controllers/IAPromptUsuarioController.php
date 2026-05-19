<?php

namespace App\Modules\Addons\IA\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\IA\Models\IAPromptUsuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class IAPromptUsuarioController extends Controller
{
    public function listar()
    {
        $userId = auth()->id();

        $propios = IAPromptUsuario::query()
            ->delUsuario($userId)
            ->orderByDesc('usos')
            ->orderByDesc('id')
            ->get();

        $publicos = IAPromptUsuario::query()
            ->publicos()
            ->where('user_id', '!=', $userId)
            ->orderByDesc('usos')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $propios->concat($publicos)->values(),
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'titulo' => ['required', 'string', 'max:250'],
            'contenido' => ['required', 'string'],
            'categoria' => ['nullable', 'string', 'max:100'],
            'es_publico' => ['nullable', 'boolean'],
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $prompt = IAPromptUsuario::create([
            'user_id' => auth()->id(),
            'titulo' => $request->input('titulo'),
            'contenido' => $request->input('contenido'),
            'categoria' => $request->input('categoria'),
            'es_publico' => (bool) $request->input('es_publico', false),
        ]);

        return response()->json(['success' => true, 'data' => $prompt]);
    }

    public function update(Request $request, int $id)
    {
        $prompt = IAPromptUsuario::where('user_id', auth()->id())->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'titulo' => ['required', 'string', 'max:250'],
            'contenido' => ['required', 'string'],
            'categoria' => ['nullable', 'string', 'max:100'],
            'es_publico' => ['nullable', 'boolean'],
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $prompt->update($request->only(['titulo', 'contenido', 'categoria', 'es_publico']));

        return response()->json(['success' => true, 'data' => $prompt->fresh()]);
    }

    public function destroy(int $id)
    {
        $prompt = IAPromptUsuario::where('user_id', auth()->id())->findOrFail($id);
        $prompt->delete();
        return response()->json(['success' => true]);
    }

    public function usar(int $id)
    {
        $prompt = IAPromptUsuario::query()
            ->where(function ($q) {
                $q->where('user_id', auth()->id())->orWhere('es_publico', true);
            })
            ->findOrFail($id);

        $prompt->incrementarUso();

        return response()->json(['success' => true, 'usos' => $prompt->usos]);
    }
}
