<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Resolvers;

use App\Modules\Addons\DocumentacionCorporativa\Contracts\ResultadoConcepto;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcConcepto;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcDocumento;

/**
 * Conceptos que se resuelven con un papel: actas, estados financieros, títulos
 * de marca, declaraciones. Lee `dc_documentos` del concepto.
 *
 * Un concepto está resuelto si tiene al menos un documento NO vencido.
 */
class DocumentoResolver extends BaseResolver
{
    public function disponible(DcConcepto $concepto, int $empresaId): bool
    {
        // Siempre disponible: la ausencia de documentos es información válida
        // (el concepto sale "vacío", con su botón de subir), no una falta de fuente.
        return true;
    }

    public function resolver(DcConcepto $concepto, int $empresaId): ResultadoConcepto
    {
        $documentos = DcDocumento::deEmpresa($empresaId)
            ->where('concepto_id', $concepto->id)
            ->orderByDesc('vigencia_fin')
            ->orderByDesc('id')
            ->get();

        $vigentes = $documentos->filter(
            fn (DcDocumento $d) => $d->estado !== DcDocumento::ESTADO_VENCIDO
        );

        $metricas = [
            'documentos_total'    => $documentos->count(),
            'documentos_vigentes' => $vigentes->count(),
            'por_vencer'          => $documentos->where('estado', DcDocumento::ESTADO_POR_VENCER)->count(),
            'vencidos'            => $documentos->where('estado', DcDocumento::ESTADO_VENCIDO)->count(),
            'obligatorio'         => (bool) $concepto->obligatorio,
        ];

        if ($documentos->isEmpty()) {
            return ResultadoConcepto::vacio(
                'dc-concepto-documentos',
                'Sin documentos cargados para este concepto.',
                $metricas
            );
        }

        $filas = $documentos->map(fn (DcDocumento $d) => [
            'titulo'          => $d->titulo,
            'archivo'         => $d->archivo_nombre_original,
            'version'         => $d->version_actual,
            'estado'          => $d->estado,
            'vigencia_inicio' => optional($d->vigencia_inicio)->toDateString(),
            'vigencia_fin'    => optional($d->vigencia_fin)->toDateString(),
            'folio'           => $d->folio,
            'contraparte'     => $d->contraparte,
            'confidencialidad' => $d->confidencialidad,
            'bytes'           => $d->bytes,
            'hash'            => $d->hash,
        ])->all();

        if ($vigentes->isEmpty()) {
            return ResultadoConcepto::parcial(
                'dc-concepto-documentos',
                $filas,
                'Todos los documentos de este concepto están vencidos.',
                $metricas
            );
        }

        return ResultadoConcepto::resuelto('dc-concepto-documentos', $filas, $metricas);
    }
}
