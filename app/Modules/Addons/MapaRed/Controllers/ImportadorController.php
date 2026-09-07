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
}
