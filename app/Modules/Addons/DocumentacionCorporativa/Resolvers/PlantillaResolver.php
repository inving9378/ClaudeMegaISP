<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Resolvers;

use App\Models\DocumentTemplate;
use App\Modules\Addons\DocumentacionCorporativa\Contracts\ResultadoConcepto;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcConcepto;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcDocumento;

/**
 * Conceptos que la empresa nunca redactó y se generan desde plantilla:
 * organigrama corporativo, carátula de expediente, acta de entrega-recepción.
 *
 * NO trae su propio motor de plantillas. Consume el que ya existe:
 * `document_templates` + `DocumentTemplateService` + dompdf (ver
 * `docs/analisis-modulo-plantillas-item-840.md`, que concluyó NO unificar).
 * Aquí sólo se resuelve QUÉ plantilla corresponde y si ya se generó.
 */
class PlantillaResolver extends BaseResolver
{
    public function disponible(DcConcepto $concepto, int $empresaId): bool
    {
        return $concepto->plantilla_id !== null
            && DocumentTemplate::whereKey($concepto->plantilla_id)->exists();
    }

    public function resolver(DcConcepto $concepto, int $empresaId): ResultadoConcepto
    {
        $plantilla = $concepto->plantilla_id
            ? DocumentTemplate::find($concepto->plantilla_id)
            : null;

        $metricas = ['obligatorio' => (bool) $concepto->obligatorio];

        if (! $plantilla) {
            return ResultadoConcepto::sinFuente(
                'Este concepto se genera desde plantilla, pero todavía no tiene una '
                . 'plantilla asignada en `document_templates`. Se crea en la fase '
                . 'correspondiente y se enlaza aquí.',
                $metricas
            );
        }

        // Lo generado se archiva como documento del concepto, igual que un papel
        // escaneado: así el expediente tiene UNA sola noción de "lo que hay".
        $generados = DcDocumento::deEmpresa($empresaId)
            ->where('concepto_id', $concepto->id)
            ->get();

        $vigentes = $generados->filter(
            fn (DcDocumento $d) => $d->estado !== DcDocumento::ESTADO_VENCIDO
        );

        $datos = [[
            'plantilla'          => $plantilla->name,
            'plantilla_id'       => $plantilla->id,
            'tipo'               => $plantilla->type,
            'documentos_generados' => $generados->count(),
            'ultimo_generado'    => optional($generados->max('created_at'))->toDateTimeString(),
        ]];

        $metricas += [
            'plantilla_id'        => $plantilla->id,
            'documentos_total'    => $generados->count(),
            'documentos_vigentes' => $vigentes->count(),
        ];

        if ($vigentes->isEmpty()) {
            return ResultadoConcepto::vacio(
                'dc-concepto-plantilla',
                'La plantilla "' . $plantilla->name . '" está lista, pero todavía no se ha '
                . 'generado el documento.',
                $metricas
            );
        }

        return ResultadoConcepto::resuelto('dc-concepto-plantilla', $datos, $metricas);
    }
}
