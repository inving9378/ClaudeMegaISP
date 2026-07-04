<?php

namespace App\Modules\Addons\WhatsAppAgent\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppFunction;
use App\Modules\Addons\WhatsAppAgent\Models\WhatsAppInstanceFunction;
use App\Modules\Addons\WhatsAppAgent\Requests\WhatsAppFunctionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Catálogo de funciones WhatsApp (Fase 3). Gate whatsapp_manage_functions
 * (via WhatsAppFunctionRequest::authorize + can() explícito en toggle/destroy).
 */
class WhatsAppFunctionController extends Controller
{
    /** GET /whatsapp/funciones — vista de gestión del catálogo. */
    public function panel()
    {
        return view('addon-whatsapp-agent::funciones');
    }

    /** GET /whatsapp/api/functions — catálogo con conteo de líneas y sus nombres. */
    public function index(): JsonResponse
    {
        $functions = WhatsAppFunction::withCount('assignments')
            ->with(['instances:id,name,slug'])
            ->orderBy('position')->orderBy('name')
            ->get();

        return response()->json($functions);
    }

    /** POST /whatsapp/api/functions */
    public function store(WhatsAppFunctionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['exclusive']  = $request->boolean('exclusive', true);
        $data['active']     = $request->boolean('active', true);
        $data['created_by'] = auth()->id();

        $function = WhatsAppFunction::create($data);

        return response()->json($function, 201);
    }

    /** PATCH /whatsapp/api/functions/{id} */
    public function update(WhatsAppFunctionRequest $request, int $id): JsonResponse
    {
        $function = WhatsAppFunction::findOrFail($id);

        $data = $request->validated();
        $data['updated_by'] = auth()->id();

        // Si se intenta marcar exclusiva una función que hoy está en varias líneas,
        // se rompería el invariante → bloquear con mensaje claro.
        if ($request->boolean('exclusive') && ! $function->exclusive) {
            $owners = WhatsAppInstanceFunction::where('function_id', $function->id)->count();
            if ($owners > 1) {
                return $this->tooManyLinesForExclusive($function->name, $owners);
            }
        }

        $function->update($data);

        return response()->json($function);
    }

    /** PATCH /whatsapp/api/functions/{id}/exclusive — toggle rápido de exclusiva. */
    public function toggleExclusive(Request $request, int $id): JsonResponse
    {
        abort_unless((bool) $request->user()?->can('whatsapp_manage_functions'), 403);

        $function = WhatsAppFunction::findOrFail($id);

        // Pasar a exclusiva estando en varias líneas rompería el invariante.
        if (! $function->exclusive) {
            $owners = WhatsAppInstanceFunction::where('function_id', $function->id)->count();
            if ($owners > 1) {
                return $this->tooManyLinesForExclusive($function->name, $owners);
            }
        }

        $function->update([
            'exclusive'  => ! $function->exclusive,
            'updated_by' => auth()->id(),
        ]);

        return response()->json($function);
    }

    /** DELETE /whatsapp/api/functions/{id} */
    public function destroy(Request $request, int $id): JsonResponse
    {
        abort_unless((bool) $request->user()?->can('whatsapp_manage_functions'), 403);

        $function = WhatsAppFunction::findOrFail($id);

        // Borrar la función quita sus asignaciones. Mass delete: NO dispara el guard de
        // "no dejar huérfana" (la función misma se va, no queda huérfana nada). Luego
        // soft-delete de la función.
        WhatsAppInstanceFunction::where('function_id', $function->id)->delete();
        $function->delete();

        return response()->json(['success' => true]);
    }

    private function tooManyLinesForExclusive(string $name, int $owners): JsonResponse
    {
        return response()->json([
            'message' => "«{$name}» está en {$owners} líneas. Déjala en una sola línea antes de marcarla exclusiva.",
        ], 422);
    }
}
