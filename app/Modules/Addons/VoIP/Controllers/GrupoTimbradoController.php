<?php

namespace App\Modules\Addons\VoIP\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\VoIP\Models\Extension;
use App\Modules\Addons\VoIP\Models\GrupoTimbrado;
use App\Modules\Addons\VoIP\Services\DialplanGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GrupoTimbradoController extends Controller
{
    public function __construct(private DialplanGeneratorService $generator) {}
    public function index()
    {
        if (! auth()->user()->can('voip.grupos.view')) {
            abort(403);
        }
        return view('addon-voip::grupos.index');
    }

    public function data(): JsonResponse
    {
        if (! auth()->user()->can('voip.grupos.view')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $grupos = GrupoTimbrado::with('extensiones')->orderBy('nombre')->get()->map(fn (GrupoTimbrado $g) => [
            'id'               => $g->id,
            'nombre'           => $g->nombre,
            'estrategia'       => $g->estrategia,
            'ring_time'        => $g->ring_time,
            'destino_fallback' => $g->destino_fallback,
            'activo'           => $g->activo,
            'miembros'         => $g->extensiones->map(fn ($e) => [
                'id'     => $e->id,
                'numero' => $e->numero,
                'nombre' => $e->nombre,
                'orden'  => $e->pivot->orden,
            ])->values(),
        ]);

        return response()->json($grupos);
    }

    public function extensionesDisponibles(): JsonResponse
    {
        if (! auth()->user()->can('voip.grupos.view')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return response()->json(
            Extension::orderBy('numero')->get(['id', 'numero', 'nombre'])
        );
    }

    public function store(Request $request): JsonResponse
    {
        if (! auth()->user()->can('voip.grupos.create')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'nombre'           => 'required|string|max:100|unique:voip_grupos_timbrado,nombre',
            'estrategia'       => 'nullable|in:ringall,hunt,memoryhunt',
            'ring_time'        => 'nullable|integer|min:5|max:120',
            'destino_fallback' => 'nullable|in:buzon,colgar,repetir',
            'activo'           => 'nullable|boolean',
            'miembros'         => 'nullable|array',
            'miembros.*.id'    => 'required|exists:voip_extensiones,id',
            'miembros.*.orden' => 'nullable|integer|min:0',
        ]);

        $grupo = GrupoTimbrado::create($data);
        $this->sincronizarMiembros($grupo, $data['miembros'] ?? []);
        $this->generarDialplan();

        return response()->json(['id' => $grupo->id], 201);
    }

    public function update(Request $request, GrupoTimbrado $grupoTimbrado): JsonResponse
    {
        if (! auth()->user()->can('voip.grupos.edit')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'nombre'           => "required|string|max:100|unique:voip_grupos_timbrado,nombre,{$grupoTimbrado->id}",
            'estrategia'       => 'nullable|in:ringall,hunt,memoryhunt',
            'ring_time'        => 'nullable|integer|min:5|max:120',
            'destino_fallback' => 'nullable|in:buzon,colgar,repetir',
            'activo'           => 'nullable|boolean',
            'miembros'         => 'nullable|array',
            'miembros.*.id'    => 'required|exists:voip_extensiones,id',
            'miembros.*.orden' => 'nullable|integer|min:0',
        ]);

        $grupoTimbrado->update($data);
        $this->sincronizarMiembros($grupoTimbrado, $data['miembros'] ?? []);
        $this->generarDialplan();

        return response()->json(['ok' => true]);
    }

    public function destroy(GrupoTimbrado $grupoTimbrado): JsonResponse
    {
        if (! auth()->user()->can('voip.grupos.delete')) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $grupoTimbrado->extensiones()->detach();
        $grupoTimbrado->delete();
        $this->generarDialplan();

        return response()->json(['ok' => true]);
    }

    private function generarDialplan(): void
    {
        try {
            $this->generator->regenerar();
        } catch (\Throwable $e) {
            Log::warning("VoIP: error regenerando dialplan desde grupos: {$e->getMessage()}");
        }
    }

    private function sincronizarMiembros(GrupoTimbrado $grupo, array $miembros): void
    {
        $sync = [];
        foreach ($miembros as $m) {
            $sync[$m['id']] = ['orden' => $m['orden'] ?? 0];
        }
        $grupo->extensiones()->sync($sync);
    }
}
