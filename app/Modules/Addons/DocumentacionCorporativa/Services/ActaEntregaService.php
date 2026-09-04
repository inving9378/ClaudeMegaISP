<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Services;

use App\Models\DocumentTemplate;
use App\Models\User;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcEntrega;
use App\Services\DocumentTemplateService;
use Barryvdh\DomPDF\Facade\Pdf;
use RuntimeException;

/**
 * Fase 5b.2 (item roadmap #811) — genera el acta de entrega-recepción en PDF
 * de una `DcEntrega` ya armada (Fase 5b.1, `EntregaService`).
 *
 * Reusa `DocumentTemplateService::validateAndReplaceTemplate()` para los
 * tokens `${data.*}` del catálogo Comun (nombre/RFC/logo de Meganet, igual
 * criterio que `PlantillaDocumentoService`) y sustituye aparte, con
 * `str_replace`, los datos propios de la entrega que ese catálogo no conoce
 * (empresa documentada, fecha, solicitante, índice, hash, quién lo generó) —
 * NO se toca `DocumentTemplateService` ni su `DATA_CLIENT_VARIABLES_VALUE`.
 *
 * Guarda el PDF en la MISMA carpeta que el ZIP de la entrega
 * (`storage/app/documentacion_corporativa/entregas/{empresa_id}/`, igual
 * convención que `EntregaService::generar()`) y actualiza
 * `dc_entregas.ruta_acta_pdf`.
 *
 * El índice solo lista apartado/concepto/estado (nunca valores) — si algún
 * día se necesitara mencionar un acceso, usar
 * `DcInventarioAcceso::CREDENCIAL_OCULTA`, nunca el valor real.
 */
class ActaEntregaService
{
    /** Nombre fijo en `document_templates` — sembrado por `PlantillasSeeder`. */
    public const TEMPLATE_NAME = 'Acta de entrega-recepción';

    public function __construct(private DocumentTemplateService $templateService)
    {
    }

    public function generar(DcEntrega $entrega, ?string $solicitante, int $userId): DcEntrega
    {
        $plantilla = DocumentTemplate::where('name', self::TEMPLATE_NAME)->first();
        if (! $plantilla) {
            throw new RuntimeException('La plantilla "' . self::TEMPLATE_NAME . '" no está registrada en document_templates.');
        }

        $resultado = $this->templateService->validateAndReplaceTemplate($plantilla->html, null, null);
        if ($resultado['status'] !== 'ok') {
            throw new RuntimeException(
                'La plantilla del acta tiene variables sin resolver: ' . implode(', ', $resultado['keys'] ?? [])
            );
        }

        $html = strtr($resultado['html'], [
            '{{DC_EMPRESA_RAZON_SOCIAL}}' => e($entrega->empresa?->razon_social ?? '—'),
            '{{DC_FECHA}}'                => optional($entrega->fecha_entrega ?? now())->format('d/m/Y H:i'),
            '{{DC_SOLICITANTE}}'          => e($solicitante ?: ($entrega->solicitud?->solicitante ?? 'Entrega interna (sin solicitud formal)')),
            '{{DC_HASH}}'                 => e($entrega->hash_sha256 ?? 'pendiente'),
            '{{DC_GENERADO_POR}}'         => e(User::find($userId)?->name ?? "Usuario #{$userId}"),
            '{{DC_INDICE_HTML}}'          => $this->indiceHtml($entrega->indice ?? []),
        ]);

        $pdfBinario = Pdf::loadHTML($html)->output();

        $dir = storage_path("app/documentacion_corporativa/entregas/{$entrega->empresa_id}");
        if (! is_dir($dir)) {
            mkdir($dir, 0770, true);
        }
        $rutaActa = $dir . '/acta_' . $entrega->id . '_' . now()->format('Ymd-His') . '.pdf';

        if (file_put_contents($rutaActa, $pdfBinario) === false) {
            throw new RuntimeException('No se pudo guardar el acta en disco.');
        }

        $entrega->update(['ruta_acta_pdf' => $rutaActa]);

        return $entrega;
    }

    private function indiceHtml(array $indice): string
    {
        if (empty($indice)) {
            return '<tr><td colspan="3">Sin conceptos incluidos en esta entrega.</td></tr>';
        }

        $rows = '';
        foreach ($indice as $item) {
            $rows .= '<tr>'
                . '<td>' . e($item['apartado_clave'] ?? '') . '</td>'
                . '<td>' . e($item['concepto'] ?? '') . '</td>'
                . '<td>' . e($item['estado_resuelto'] ?? '') . '</td>'
                . '</tr>';
        }

        return $rows;
    }
}
