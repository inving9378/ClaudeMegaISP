<?php

namespace App\Modules\Addons\Talento\Services;

use App\Modules\Addons\Talento\Models\TalentoWorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * UNI-T L3b — regla K2 (#905 q5): corregir la PROPIA instalación dentro de la ventana no suma puntos
 * (sin clawback: nada retroactivo, las semanas cerradas no se recalculan). Detrás de
 * unificacion.interruptores.garantia_cero_puntos_propia; apagado = 0 puntos excluidos (como hoy).
 * Si existe una revisión L2 resuelta que atribuye la causa a algo distinto del trabajo original
 * (externa / cliente / ninguna), la reparación SÍ cuenta.
 */
class GarantiaPropiaService
{
    public function activo(): bool
    {
        return (bool) config('unificacion.interruptores.garantia_cero_puntos_propia');
    }

    /** Puntos de OTs de reparación/garantía del colaborador que NO cuentan en la ventana. */
    public function puntosExcluidosWo(int $colaboradorId, array $window): int
    {
        if (! $this->activo()) {
            return 0;
        }
        $meses   = max(1, (int) config('unificacion.interruptores.garantia_ventana_meses', 6));
        $tiposRep = DB::table('talento_work_order_types')->where('name', 'like', '%garant%')->pluck('id');
        $tiposIns = DB::table('talento_work_order_types')->where('inicia_garantia', true)->pluck('id');
        if ($tiposRep->isEmpty() || $tiposIns->isEmpty()) {
            return 0;
        }

        $reparaciones = TalentoWorkOrder::where('colaborador_id', $colaboradorId)
            ->validatedBillable()
            ->whereIn('type_id', $tiposRep)
            ->whereNotNull('client_id')
            ->whereBetween('validated_at', [$window['start_instant'], $window['end_instant']])
            ->get(['id', 'client_id', 'points', 'validated_at']);

        $excluidos = 0;
        foreach ($reparaciones as $rep) {
            $instalacion = TalentoWorkOrder::where('colaborador_id', $colaboradorId)
                ->where('client_id', $rep->client_id)
                ->whereIn('type_id', $tiposIns)
                ->where('status', 'validated')
                ->whereNotNull('validated_at')
                ->where('validated_at', '<=', $rep->validated_at)
                ->where('validated_at', '>=', $rep->validated_at->copy()->subMonths($meses))
                ->orderByDesc('validated_at')
                ->first(['id']);
            if ($instalacion && ! $this->revisionLaExonera((int) $instalacion->id, (int) $rep->client_id)) {
                $excluidos += (int) $rep->points;
            }
        }

        return $excluidos;
    }

    /** true si una revisión L2 resuelta atribuye la causa a algo distinto del trabajo original. */
    private function revisionLaExonera(int $instalacionId, int $clientId): bool
    {
        if (! Schema::hasTable('talento_warranty_reviews')) {
            return false;
        }
        return DB::table('talento_warranty_reviews as r')
            ->join('talento_warranty_events as e', 'e.id', '=', 'r.warranty_event_id')
            ->where('e.orden_origen', 'work_order')->where('e.orden_id', $instalacionId)->where('e.client_id', $clientId)
            ->where('r.estado', 'resuelta')
            ->where('r.responsabilidad', '!=', 'trabajo_original')
            ->exists();
    }
}
