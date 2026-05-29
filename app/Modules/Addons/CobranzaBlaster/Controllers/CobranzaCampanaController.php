<?php

namespace App\Modules\Addons\CobranzaBlaster\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\CobranzaBlaster\Models\CobranzaCampana;
use App\Modules\Addons\CobranzaBlaster\Models\CobranzaLlamada;
use App\Modules\Addons\CobranzaBlaster\Services\CobranzaCampanaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CobranzaCampanaController extends Controller
{
    public function __construct(private CobranzaCampanaService $service) {}

    public function index(): JsonResponse
    {
        $campanas = CobranzaCampana::withCount('llamadas')
            ->orderByDesc('created_at')
            ->get();

        return response()->json($campanas);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nombre'                  => 'required|string|max:255',
            'fecha_inicio'            => 'nullable|date',
            'fecha_fin'               => 'nullable|date|after_or_equal:fecha_inicio',
            'hora_inicio'             => 'nullable|date_format:H:i',
            'hora_fin'                => 'nullable|date_format:H:i',
            'max_intentos'            => 'nullable|integer|min:1|max:10',
            'minutos_entre_intentos'  => 'nullable|integer|min:30',
            'audio_mensaje'           => 'nullable|string',
            'dias_vencimiento'        => 'nullable|integer|min:0',
            'notas'                   => 'nullable|string',
        ]);

        $campana = CobranzaCampana::create($data);

        return response()->json($campana, 201);
    }

    public function show(CobranzaCampana $campana): JsonResponse
    {
        return response()->json($campana->load('llamadas'));
    }

    public function update(Request $request, CobranzaCampana $campana): JsonResponse
    {
        if ($campana->estado === 'activa') {
            return response()->json(['error' => 'No se puede editar una campaña activa.'], 422);
        }

        $data = $request->validate([
            'nombre'                  => 'sometimes|string|max:255',
            'fecha_inicio'            => 'nullable|date',
            'fecha_fin'               => 'nullable|date',
            'hora_inicio'             => 'nullable|date_format:H:i',
            'hora_fin'                => 'nullable|date_format:H:i',
            'max_intentos'            => 'nullable|integer|min:1|max:10',
            'minutos_entre_intentos'  => 'nullable|integer|min:30',
            'audio_mensaje'           => 'nullable|string',
            'dias_vencimiento'        => 'nullable|integer|min:0',
            'notas'                   => 'nullable|string',
        ]);

        $campana->update($data);

        return response()->json($campana);
    }

    public function activar(CobranzaCampana $campana): JsonResponse
    {
        if (!in_array($campana->estado, ['borrador', 'pausada'])) {
            return response()->json(['error' => 'Solo se puede activar desde borrador o pausada.'], 422);
        }

        $this->service->activarCampana($campana);

        return response()->json(['ok' => true, 'estado' => $campana->fresh()->estado]);
    }

    public function pausar(CobranzaCampana $campana): JsonResponse
    {
        if ($campana->estado !== 'activa') {
            return response()->json(['error' => 'La campaña no está activa.'], 422);
        }

        $this->service->pausarCampana($campana);

        return response()->json(['ok' => true]);
    }

    public function completar(CobranzaCampana $campana): JsonResponse
    {
        $this->service->completarCampana($campana);

        return response()->json(['ok' => true]);
    }

    public function estadisticas(CobranzaCampana $campana): JsonResponse
    {
        return response()->json($this->service->estadisticas($campana));
    }

    public function llamadas(Request $request, CobranzaCampana $campana): JsonResponse
    {
        $llamadas = $campana->llamadas()
            ->with('client.client_main_information')
            ->when($request->estado, fn ($q) => $q->where('estado', $request->estado))
            ->orderByDesc('ultimo_intento_at')
            ->paginate(50);

        return response()->json($llamadas);
    }

    public function excluirLlamada(CobranzaCampana $campana, CobranzaLlamada $llamada): JsonResponse
    {
        if ($llamada->campana_id !== $campana->id) {
            return response()->json(['error' => 'Llamada no pertenece a esta campaña.'], 422);
        }

        $llamada->update(['estado' => 'excluida']);

        return response()->json(['ok' => true]);
    }
}
