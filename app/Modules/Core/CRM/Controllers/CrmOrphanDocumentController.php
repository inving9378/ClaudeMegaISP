<?php

namespace App\Modules\Core\CRM\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\CRM\Support\CrmOrphanDocumentDetector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reporte de solo lectura de document_crms huérfanos (#9990244, Fase 1/2 de
 * #9990085). Reusa CrmOrphanDocumentDetector (#9990084) — no reimplementa el
 * criterio de detección. Sin frontend Vue propio: la vista de index()
 * queda como placeholder hasta el sub-item hermano (Fase 2).
 */
class CrmOrphanDocumentController extends Controller
{
    public function index()
    {
        return view('core-crm::documentos_huerfanos');
    }

    public function data(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->filtered($request)->values(),
        ]);
    }

    public function csv(Request $request): StreamedResponse
    {
        $rows = $this->filtered($request);
        $filename = 'crm-documentos-huerfanos-' . now()->format('Ymd_His') . '.csv';

        return new StreamedResponse(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'crm_id', 'title', 'motivo', 'created_at']);
            foreach ($rows as $o) {
                fputcsv($out, [$o->id, $o->crm_id, $o->title, $o->motivo, $o->created_at]);
            }
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function filtered(Request $request)
    {
        $orphans = CrmOrphanDocumentDetector::detect();

        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return $orphans;
        }

        if (is_numeric($q) && (int) $q == $q) {
            $crmId = (int) $q;
            return $orphans->filter(fn ($o) => $o->crm_id === $crmId);
        }

        $needle = mb_strtolower($q);
        return $orphans->filter(fn ($o) => $o->title !== null && str_contains(mb_strtolower($o->title), $needle));
    }
}
