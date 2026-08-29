<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Services;

use App\Models\DocumentTemplate;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcConcepto;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcDocumento;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcDocumentoVersion;
use App\Services\DocumentTemplateService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Fase 2d (item roadmap #737) — produce el PDF de un concepto tipo `plantilla`
 * y lo archiva como `DcDocumento` del concepto, igual que un papel escaneado
 * (mismo criterio que ya documenta `PlantillaResolver`).
 *
 * Reusa `DocumentTemplateService::validateAndReplaceTemplate()` para resolver
 * las variables `${data.*}` de información de empresa — NO reimplementa un
 * segundo motor de sustitución (`docs/analisis-modulo-plantillas-item-840.md`
 * ya concluyó no unificar, sólo consumir). La conversión a PDF usa el mismo
 * dompdf que ya usa `BaseResolver::exportar()` en este módulo: los métodos de
 * `DocumentTemplateService` que sí escriben a disco (`saveDocumentTemplate`)
 * lo hacen al disco PÚBLICO, y el expediente corporativo es privado por diseño.
 *
 * Guarda en disco `local` bajo la MISMA convención de carpeta que usará el
 * repositorio documental (Fase 2a, item #734: `documentacion_corporativa/
 * {empresa}/{apartado}/{concepto}/{uuid}.ext`) — item #734 seguía sin
 * integrarse a `main` cuando se escribió esto, así que aquí se implementa el
 * guardado mínimo directo sobre `DcDocumento`/`DcDocumentoVersion` en vez de
 * reusar su servicio.
 */
class PlantillaDocumentoService
{
    public function __construct(private DocumentTemplateService $templateService)
    {
    }

    public function generar(DcConcepto $concepto, int $empresaId, int $userId): DcDocumento
    {
        if ($concepto->plantilla_id === null) {
            throw new RuntimeException('Este concepto no tiene una plantilla asignada todavía.');
        }

        $plantilla = DocumentTemplate::find($concepto->plantilla_id);

        if (! $plantilla) {
            throw new RuntimeException('La plantilla asignada a este concepto ya no existe.');
        }

        // $data=null, $module=null a propósito: las variables `${data.*}` de este
        // motor son de información de EMPRESA (Comun), que se resuelven siempre
        // sin necesitar datos de cliente/CRM — ver `DocumentTemplateService`.
        $resultado = $this->templateService->validateAndReplaceTemplate($plantilla->html, null, null);

        if ($resultado['status'] !== 'ok') {
            throw new RuntimeException(
                'La plantilla "' . $plantilla->name . '" tiene variables sin resolver: '
                . implode(', ', $resultado['keys'] ?? [])
            );
        }

        $pdfBinario = Pdf::loadHTML($resultado['html'])->output();

        $uuid       = (string) Str::uuid();
        $directorio = $this->directorioDestino($empresaId, $concepto);
        $ruta       = "{$directorio}/{$uuid}.pdf";
        $hash       = hash('sha256', $pdfBinario);
        $bytes      = strlen($pdfBinario);

        if (Storage::disk('local')->put($ruta, $pdfBinario) === false) {
            throw new RuntimeException('No se pudo guardar el documento generado en disco.');
        }

        try {
            return DB::transaction(function () use (
                $concepto, $empresaId, $uuid, $plantilla, $hash, $bytes, $userId
            ) {
                $nombreArchivo = Str::slug($plantilla->name) . '.pdf';

                $documento = DcDocumento::create([
                    'empresa_id'              => $empresaId,
                    'concepto_id'             => $concepto->id,
                    'titulo'                  => $plantilla->name,
                    'archivo_uuid'            => $uuid,
                    'archivo_nombre_original' => $nombreArchivo,
                    'mime'                    => 'application/pdf',
                    'bytes'                   => $bytes,
                    'hash'                    => $hash,
                    'version_actual'          => 1,
                    'confidencialidad'        => $concepto->confidencialidad,
                    'notas'                   => 'Generado desde la plantilla "' . $plantilla->name . '".',
                    'subido_por'              => $userId,
                ]);

                DcDocumentoVersion::create([
                    'empresa_id'              => $empresaId,
                    'documento_id'            => $documento->id,
                    'version'                 => 1,
                    'archivo_uuid'            => $uuid,
                    'archivo_nombre_original' => $nombreArchivo,
                    'mime'                    => 'application/pdf',
                    'bytes'                   => $bytes,
                    'hash'                    => $hash,
                    'subido_por'              => $userId,
                    'nota_cambio'             => 'Generado desde plantilla.',
                    'created_at'              => now(),
                ]);

                return $documento;
            });
        } catch (Throwable $e) {
            // La transacción no llegó a puerto: el archivo huérfano no se queda.
            Storage::disk('local')->delete($ruta);
            throw $e;
        }
    }

    private function directorioDestino(int $empresaId, DcConcepto $concepto): string
    {
        $apartadoClave = mb_strtolower($concepto->apartado?->clave ?? 'sin-apartado');
        $conceptoSlug  = $concepto->slug ?: Str::slug($concepto->nombre);

        return "documentacion_corporativa/{$empresaId}/{$apartadoClave}/{$conceptoSlug}";
    }
}
