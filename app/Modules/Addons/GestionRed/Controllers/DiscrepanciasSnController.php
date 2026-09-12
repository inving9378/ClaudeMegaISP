<?php

namespace App\Modules\Addons\GestionRed\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\GestionRed\Services\DiscrepanciaSnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reporte de discrepancias SN captura-manual vs. OLT (item #9990837, Fase 4a).
 * Solo lectura: index paginado + export CSV, ambos por categoría.
 */
class DiscrepanciasSnController extends Controller
{
    public function __construct(private DiscrepanciaSnService $service)
    {
    }

    /**
     * Fase 4b (#9990850): pantalla del reporte (3 tabs + export CSV).
     */
    public function panel()
    {
        return view('addon-gestion-red::discrepancias-sn.index');
    }

    public function index(Request $request, string $categoria): JsonResponse
    {
        if (!in_array($categoria, DiscrepanciaSnService::CATEGORIAS, true)) {
            return response()->json(['message' => 'Categoría desconocida.'], 404);
        }

        $page = max(1, (int) $request->input('page', 1));
        $perPage = (int) $request->input('per_page', 50);

        $paginator = $this->service->paginar($categoria, $page, $perPage);

        return response()->json([
            'categoria' => $categoria,
            'data' => $paginator->items(),
            'total' => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
        ]);
    }

    public function export(string $categoria): StreamedResponse|JsonResponse
    {
        if (!in_array($categoria, DiscrepanciaSnService::CATEGORIAS, true)) {
            return response()->json(['message' => 'Categoría desconocida.'], 404);
        }

        $filas = $this->service->todas($categoria);
        $filename = "discrepancias-sn-{$categoria}-" . now()->format('Ymd-His') . '.csv';

        return new StreamedResponse(function () use ($filas) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM Excel

            if (!empty($filas)) {
                fputcsv($out, array_keys($filas[0]));
                foreach ($filas as $fila) {
                    fputcsv($out, $fila);
                }
            }

            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
