<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcConcepto;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcDocumento;
use App\Modules\Addons\DocumentacionCorporativa\Services\BitacoraService;
use App\Modules\Addons\DocumentacionCorporativa\Services\CompletitudService;
use App\Modules\Addons\DocumentacionCorporativa\Services\DocumentoService;
use App\Modules\Addons\DocumentacionCorporativa\Services\EmpresaContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Repositorio documental (Fase 2a, item roadmap #767 — backend recuperado del
 * item #734, aprobado_irving nivel C, cuya rama nunca se integró a main):
 * subir, versionar, descargar y eliminar los documentos de un concepto.
 *
 * Misma doble puerta que el resto del módulo: `check_route_permission` gatea
 * la ENTRADA (`documentacion-corporativa.view`); aquí se gatea la ACCIÓN con
 * los tres permisos declarados en `module.json`
 * (`.documento.upload` / `.documento.download` / `.documento.delete`).
 *
 * Confidencialidad `critica`: además del permiso normal, sólo el rol
 * `super-administrator` puede descargar o listar versiones.
 */
class DocumentoController extends Controller
{
    public function __construct(
        private EmpresaContextService $empresas,
        private DocumentoService $documentos,
        private CompletitudService $completitud,
        private BitacoraService $bitacora,
    ) {
    }

    /** Sube un documento nuevo (sin `documento_id`) o una versión nueva sobre uno existente. */
    public function store(Request $request): JsonResponse
    {
        $this->autorizar('documentacion-corporativa.documento.upload', 'subir documentos');
        $empresa = $this->empresas->actual();

        $validado = $request->validate($this->reglasSubida());

        $concepto = DcConcepto::deEmpresa($empresa->id)->activos()->findOrFail($validado['concepto_id']);

        $documentoExistente = null;
        if (! empty($validado['documento_id'])) {
            $documentoExistente = DcDocumento::deEmpresa($empresa->id)
                ->where('concepto_id', $concepto->id)
                ->findOrFail($validado['documento_id']);
        }

        $documento = $this->documentos->subir(
            $concepto,
            $empresa->id,
            $request->file('archivo'),
            $validado,
            (int) auth()->id(),
            $documentoExistente
        );

        return response()->json($documento->fresh(), $documentoExistente ? 200 : 201);
    }

    /**
     * Carga masiva (drag&drop): varios archivos en un solo POST, cada uno con
     * su propio `concepto_id` (y opcionalmente `documento_id`) alineado por
     * índice. Un archivo que falla NO tumba a los demás — se reporta aparte.
     */
    public function storeLote(Request $request): JsonResponse
    {
        $this->autorizar('documentacion-corporativa.documento.upload', 'subir documentos');
        $empresa = $this->empresas->actual();

        $validado = $request->validate([
            'archivos'        => ['required', 'array', 'min:1'],
            'archivos.*'      => $this->reglasArchivoIndividual(),
            'concepto_ids'    => ['required', 'array'],
            'concepto_ids.*'  => ['required', 'integer'],
            'documento_ids'   => ['nullable', 'array'],
            'documento_ids.*' => ['nullable', 'integer'],
        ]);

        if (count($validado['archivos']) !== count($validado['concepto_ids'])) {
            return response()->json([
                'message' => 'Cada archivo debe traer su concepto_id (mismo número de elementos).',
            ], 422);
        }

        $resultados = [];

        foreach ($validado['archivos'] as $indice => $archivo) {
            $conceptoId  = $validado['concepto_ids'][$indice];
            $documentoId = $validado['documento_ids'][$indice] ?? null;

            try {
                $concepto = DcConcepto::deEmpresa($empresa->id)->activos()->findOrFail($conceptoId);

                $documentoExistente = $documentoId
                    ? DcDocumento::deEmpresa($empresa->id)->where('concepto_id', $concepto->id)->findOrFail($documentoId)
                    : null;

                $documento = $this->documentos->subir(
                    $concepto,
                    $empresa->id,
                    $archivo,
                    [],
                    (int) auth()->id(),
                    $documentoExistente
                );

                $resultados[] = [
                    'ok'           => true,
                    'concepto_id'  => $conceptoId,
                    'documento_id' => $documento->id,
                    'version'      => $documento->version_actual,
                ];
            } catch (Throwable $e) {
                $resultados[] = [
                    'ok'          => false,
                    'concepto_id' => $conceptoId,
                    'error'       => $e->getMessage(),
                ];
            }
        }

        return response()->json(['resultados' => $resultados]);
    }

    /** Timeline de versiones de un documento. */
    public function versiones(int $id): JsonResponse
    {
        $this->autorizar('documentacion-corporativa.documento.download', 'descargar documentos');
        $empresa = $this->empresas->actual();

        $documento = DcDocumento::deEmpresa($empresa->id)->findOrFail($id);
        $this->negarSiCriticoSinRol($documento);

        return response()->json([
            'documento' => $documento,
            'versiones' => $documento->versiones()->with('subidoPor:id,name')->get(),
        ]);
    }

    /** Descarga la versión VIGENTE. */
    public function descargar(int $id): StreamedResponse
    {
        return $this->servirArchivo($id, null);
    }

    /** Descarga una versión histórica específica. */
    public function descargarVersion(int $id, int $version): StreamedResponse
    {
        return $this->servirArchivo($id, $version);
    }

    /** Elimina (soft delete) un documento. El archivo en disco se conserva. */
    public function destroy(int $id): JsonResponse
    {
        $this->autorizar('documentacion-corporativa.documento.delete', 'eliminar documentos');
        $empresa = $this->empresas->actual();

        $documento = DcDocumento::deEmpresa($empresa->id)->findOrFail($id);
        $documento->delete();

        $this->completitud->invalidar($empresa->id);

        return response()->json(['ok' => true]);
    }

    private function servirArchivo(int $id, ?int $version): StreamedResponse
    {
        $this->autorizar('documentacion-corporativa.documento.download', 'descargar documentos');
        $empresa = $this->empresas->actual();

        $documento = DcDocumento::deEmpresa($empresa->id)->findOrFail($id);
        $this->negarSiCriticoSinRol($documento);

        if ($version !== null) {
            $fila = $documento->versiones()->where('version', $version)->firstOrFail();
        } else {
            $fila = $documento;
        }

        $ruta = $fila->ruta_archivo;
        abort_unless($ruta && Storage::disk('local')->exists($ruta), 404, 'El archivo no existe en disco.');

        // Bitácora ANTES de servir: si el registro falla, el archivo no sale.
        $this->bitacora->descargar($empresa->id, $documento->id, $documento->concepto_id, [
            'version' => $version ?? $documento->version_actual,
        ]);

        return Storage::disk('local')->download($ruta, $fila->archivo_nombre_original);
    }

    private function reglasSubida(): array
    {
        return [
            'archivo'          => $this->reglasArchivoIndividual(),
            'concepto_id'      => ['required', 'integer'],
            'documento_id'     => ['nullable', 'integer'],
            'titulo'           => ['nullable', 'string', 'max:255'],
            'vigencia_inicio'  => ['nullable', 'date'],
            'vigencia_fin'     => ['nullable', 'date'],
            'folio'            => ['nullable', 'string', 'max:255'],
            'contraparte'      => ['nullable', 'string', 'max:255'],
            'confidencialidad' => ['nullable', Rule::in(DcConcepto::CONFIDENCIALIDADES)],
            'notas'            => ['nullable', 'string'],
            'nota_cambio'      => ['nullable', 'string'],
        ];
    }

    private function reglasArchivoIndividual(): array
    {
        $config      = config('documentacion_corporativa.documentos', []);
        $maxBytes    = (int) ($config['max_bytes'] ?? 20 * 1024 * 1024);
        $maxKb       = (int) floor($maxBytes / 1024);
        $extensiones = implode(',', $config['extensiones_permitidas'] ?? ['pdf']);

        return ['required', 'file', "max:{$maxKb}", "mimes:{$extensiones}"];
    }

    private function autorizar(string $permiso, string $accion): void
    {
        abort_unless(
            auth()->user()?->can($permiso),
            403,
            "No tienes permiso para {$accion}."
        );
    }

    private function negarSiCriticoSinRol(DcDocumento $documento): void
    {
        if ($documento->confidencialidad === 'critica' && ! auth()->user()?->hasRole('super-administrator')) {
            abort(403, 'Este documento es de confidencialidad crítica: solo super-administrator puede acceder.');
        }
    }
}
