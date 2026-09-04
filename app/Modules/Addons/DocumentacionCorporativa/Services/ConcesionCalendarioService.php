<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Services;

use App\Modules\Addons\DocumentacionCorporativa\Models\DcConcesion;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcConcesionPago;

/**
 * Calendario regulatorio del apartado XIII: concesiones agrupadas por escalón
 * de alerta (90/60/30/7 días) y pagos vencidos u próximos a vencer.
 *
 * Una sola fuente de verdad para "qué tan urgente es esto": los mismos
 * accessors del modelo (`nivel_alerta`, `semaforo`) que ve la ficha individual,
 * no una segunda regla que puede divergir.
 */
class ConcesionCalendarioService
{
    public function calendario(int $empresaId): array
    {
        $concesiones = DcConcesion::deEmpresa($empresaId)
            ->with('responsable:id,name')
            ->orderBy('vigencia_fin')
            ->get();

        $grupos = ['vencidas' => [], 90 => [], 60 => [], 30 => [], 7 => []];

        foreach ($concesiones as $concesion) {
            $dias = $concesion->dias_para_vencer;
            if ($dias === null || $dias > 90) {
                continue;
            }

            $fila = $this->presentarConcesion($concesion);
            $dias < 0 ? $grupos['vencidas'][] = $fila : $grupos[$concesion->nivel_alerta][] = $fila;
        }

        $pagosPendientes = DcConcesionPago::deEmpresa($empresaId)
            ->pendientes()
            ->whereNotNull('fecha_vencimiento')
            ->where('fecha_vencimiento', '<=', now()->addDays(90)->endOfDay())
            ->with('concesion:id,tipo,folio,autoridad')
            ->orderBy('fecha_vencimiento')
            ->get()
            ->map(fn (DcConcesionPago $pago) => [
                'id'                => $pago->id,
                'concesion_id'      => $pago->concesion_id,
                'concepto'          => $pago->concepto,
                'periodo'           => $pago->periodo,
                'monto'             => $pago->monto,
                'fecha_vencimiento' => $pago->fecha_vencimiento?->toDateString(),
                'vencido'           => $pago->fecha_vencimiento?->lt(now()->startOfDay()) ?? false,
                'concesion'         => $pago->concesion?->folio ?? $pago->concesion?->tipo,
            ])
            ->values();

        return [
            'umbrales'         => DcConcesion::UMBRALES_ALERTA,
            'vigencias'        => $grupos,
            'pagos_pendientes' => $pagosPendientes,
            'total_alertas'    => array_sum(array_map('count', $grupos)),
            'calculado_at'     => now()->toDateTimeString(),
        ];
    }

    private function presentarConcesion(DcConcesion $concesion): array
    {
        return [
            'id'                => $concesion->id,
            'tipo'              => $concesion->tipo,
            'folio'             => $concesion->folio,
            'autoridad'         => $concesion->autoridad,
            'vigencia_fin'      => $concesion->vigencia_fin?->toDateString(),
            'dias_para_vencer'  => $concesion->dias_para_vencer,
            'nivel_alerta'      => $concesion->nivel_alerta,
            'estado_tramite'    => $concesion->estado_tramite,
            'semaforo'          => $concesion->semaforo,
            'responsable'       => $concesion->responsable?->name,
        ];
    }
}
