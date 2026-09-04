<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Talento\Models\TalentoEmployeeDocument;
use Illuminate\Http\Response;

/**
 * Item roadmap #871 (Expediente RH — Hijo D2), fase C. Endpoints de solo lectura sobre los
 * documentos ya generados por EmployeeDocumentPackageService (fases A+B). Sin edición ni
 * regeneración manual desde aquí — regla anti-desincronización del item padre.
 *
 * Gate 'talento.expediente.view' (NO 'talento.view'): el HTML renderizado trae CURP/NSS/RFC/
 * salario/domicilio (ver EmployeeDocumentPackageService::empleadoData), los mismos campos
 * sensibles que el item #199 aisló detrás de este permiso propio en TalentoColaboradorController.
 */
class TalentoEmployeeDocumentController extends Controller
{
    public function forColaborador($colaboradorId)
    {
        $this->authorize('talento.expediente.view');

        $documentos = TalentoEmployeeDocument::where('colaborador_id', $colaboradorId)
            ->with('template:id,name')
            ->orderBy('id')
            ->get(['id', 'colaborador_id', 'template_id', 'status', 'generated_at']);

        return response()->json($documentos);
    }

    public function show($colaboradorId, $docId)
    {
        $this->authorize('talento.expediente.view');

        $documento = TalentoEmployeeDocument::where('colaborador_id', $colaboradorId)
            ->where('id', $docId)
            ->firstOrFail();

        return response($documento->rendered_html, Response::HTTP_OK)
            ->header('Content-Type', 'text/html');
    }
}
