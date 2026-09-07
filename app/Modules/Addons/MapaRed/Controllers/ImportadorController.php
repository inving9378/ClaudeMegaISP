<?php

namespace App\Modules\Addons\MapaRed\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MapaRed\Services\ImportadorRedService;
use Illuminate\Http\Request;

/**
 * MR-25 (item #961) — importador KML/KMZ con previsualización + confirmación.
 * GeoJSON/CSV se agregan en items de seguimiento reusando el mismo contrato de
 * `previsualizar()`/`confirmar()` de `ImportadorRedService` (solo cambia el parser).
 */
class ImportadorController extends Controller
{
    public function __construct(private ImportadorRedService $service)
    {
    }

    public function previsualizarKml(Request $request)
    {
        $request->validate(['file' => 'required|file']);
        $file = $request->file('file');

        try {
            $resultado = $this->service->previsualizar($file->getRealPath(), $file->getMimeType());
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        return response()->json($resultado);
    }

    public function confirmarKml(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'project_id' => 'nullable|integer|exists:mapared_proyects,id',
        ]);

        $reporte = $this->service->confirmar($validated['items'], $validated['project_id'] ?? null);

        return response()->json($reporte);
    }

    /**
     * MR-25 Fase 3a (item #9990443) — mismo contrato preview/commit, parser GeoJSON.
     */
    public function previsualizarGeoJson(Request $request)
    {
        $request->validate(['file' => 'required|file']);
        $file = $request->file('file');

        try {
            $resultado = $this->service->previsualizarGeoJson($file->getRealPath());
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        return response()->json($resultado);
    }

    /**
     * `confirmar()` es formato-agnóstica (recibe la misma lista de items ya clasificados),
     * así que el commit de GeoJSON reusa exactamente `confirmarKml()`.
     */
    public function confirmarGeoJson(Request $request)
    {
        return $this->confirmarKml($request);
    }

    /**
     * MR-25 Fase 3b (item #9990444) — CSV. A diferencia de KML/GeoJSON, aquí el preview puede
     * devolver un reporte de errores por fila (política de Irving: valida TODO antes, aborta
     * si hay cualquier error) — se distingue del preview normal por la clave `errores` y
     * viaja como 422 para que el frontend lo trate distinto de un preview exitoso.
     */
    public function previsualizarCsv(Request $request)
    {
        $request->validate(['file' => 'required|file']);
        $file = $request->file('file');

        try {
            $resultado = $this->service->previsualizarCsv($file->getRealPath());
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        if (isset($resultado['errores'])) {
            return response()->json($resultado, 422);
        }

        return response()->json($resultado);
    }

    /**
     * Commit del CSV: transacción all-or-nothing + lotes de 500 (`confirmarCsv()`), a
     * diferencia de `confirmar()` que usa KML/GeoJSON sin ese envoltorio.
     */
    public function confirmarCsv(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'project_id' => 'nullable|integer|exists:mapared_proyects,id',
        ]);

        $reporte = $this->service->confirmarCsv($validated['items'], $validated['project_id'] ?? null);

        return response()->json($reporte);
    }
}
