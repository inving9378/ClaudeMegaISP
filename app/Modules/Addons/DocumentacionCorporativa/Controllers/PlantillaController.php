<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcConcepto;
use App\Modules\Addons\DocumentacionCorporativa\Services\EmpresaContextService;
use App\Modules\Addons\DocumentacionCorporativa\Services\PlantillaDocumentoService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Fase 2d (item roadmap #737) — botón "Generar documento" de un concepto tipo
 * `plantilla` (organigrama, relación de activos y pasivos, ...).
 *
 * Mismo permiso que subir un documento a mano
 * (`documentacion-corporativa.documento.upload`): generar desde plantilla ES
 * archivar un documento en el expediente, sólo que el origen es una plantilla
 * en vez de un archivo que sube el usuario.
 */
class PlantillaController extends Controller
{
    public function __construct(
        private EmpresaContextService $empresas,
        private PlantillaDocumentoService $generador,
    ) {
    }

    public function generar(string $clave): JsonResponse
    {
        abort_unless(
            auth()->user()?->can('documentacion-corporativa.documento.upload'),
            403,
            'No tienes permiso para generar documentos en el expediente.'
        );

        $empresa = $this->empresas->actual();

        $concepto = DcConcepto::deEmpresa($empresa->id)->activos()
            ->where('slug', $clave)
            ->where('tipo_resolvedor', 'plantilla')
            ->firstOrFail();

        try {
            $documento = $this->generador->generar($concepto, $empresa->id, (int) auth()->id());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'documento_id' => $documento->id,
            'titulo'       => $documento->titulo,
        ], 201);
    }
}
