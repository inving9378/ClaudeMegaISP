<?php

namespace App\Modules\Addons\MapaRed\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MapaRed\Models\MapaRedSectorInalambrico;
use App\Modules\Addons\MapaRed\Services\SectorInalambricoImportadorService;
use Illuminate\Http\Request;

/**
 * MR-26 Fase 3 (item roadmap #9990524) — sectores inalámbricos (azimut/apertura/alcance/
 * altura): CRUD + import CSV/GeoJSON + capa GeoJSON de consumo. Gateado por el mismo permiso
 * `mapa_red_view` que el resto de `/mapa-red/api/**` (patrón de CoberturaController). D29:
 * sin UI de dibujo nueva aquí — el frontend que dibuja el cono es Fase 4.
 */
class SectoresController extends Controller
{
    public function __construct(private SectorInalambricoImportadorService $importador)
    {
    }

    /**
     * GeoJSON: un Feature Point por sector con properties completas (DoD del item).
     */
    public function index()
    {
        $sectores = MapaRedSectorInalambrico::query()->activos()->get();

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $sectores->map(fn($s) => $s->toGeoJsonFeature())->values(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'azimut_grados' => 'required|numeric|min:0|max:360',
            'apertura_grados' => 'required|numeric|min:0|max:360',
            'alcance_metros' => 'required|integer|min:1',
            'altura_metros' => 'nullable|numeric',
            'activo' => 'boolean',
        ]);

        $sector = MapaRedSectorInalambrico::create($validated);

        return response()->json($sector, 201);
    }

    public function update(Request $request, int $id)
    {
        $sector = MapaRedSectorInalambrico::findOrFail($id);

        $validated = $request->validate([
            'nombre' => 'sometimes|required|string|max:255',
            'lat' => 'sometimes|required|numeric|between:-90,90',
            'lng' => 'sometimes|required|numeric|between:-180,180',
            'azimut_grados' => 'sometimes|required|numeric|min:0|max:360',
            'apertura_grados' => 'sometimes|required|numeric|min:0|max:360',
            'alcance_metros' => 'sometimes|required|integer|min:1',
            'altura_metros' => 'nullable|numeric',
            'activo' => 'boolean',
        ]);

        $sector->update($validated);

        return response()->json($sector);
    }

    public function destroy(int $id)
    {
        MapaRedSectorInalambrico::findOrFail($id)->delete();

        return response()->json(['ok' => true]);
    }

    public function previsualizarCsv(Request $request)
    {
        $request->validate(['file' => 'required|file']);
        $file = $request->file('file');

        try {
            $resultado = $this->importador->previsualizarCsv($file->getRealPath());
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        if (isset($resultado['errores'])) {
            return response()->json($resultado, 422);
        }

        return response()->json($resultado);
    }

    public function previsualizarGeoJson(Request $request)
    {
        $request->validate(['file' => 'required|file']);
        $file = $request->file('file');

        try {
            $resultado = $this->importador->previsualizarGeoJson($file->getRealPath());
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        return response()->json($resultado);
    }

    public function confirmar(Request $request)
    {
        $validated = $request->validate(['items' => 'required|array']);

        $reporte = $this->importador->confirmar($validated['items']);

        return response()->json($reporte);
    }
}
