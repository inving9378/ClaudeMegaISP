<?php

namespace App\Modules\Addons\Talento\Services;

use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoLedgerEntry;
use App\Modules\Addons\Talento\Models\TalentoLiquidation;
use Illuminate\Support\Facades\DB;

/**
 * Punto único del cálculo de «Mi dinero» (UNI-T H2): lo usan el portal web, la pestaña Compensación
 * de la app y la compensación semanal de la app móvil, para que los importes no puedan divergir.
 * Cada API conserva su forma de respuesta y toma de aquí solo lo que necesita.
 */
class CompensacionService
{
    /** Libro de la ventana agrupado por concepto + neto + si la semana ya se calculó. */
    public function cuentaCorriente(int $colaboradorId, array $w): array
    {
        $rows = TalentoLedgerEntry::where('colaborador_id', $colaboradorId)
            ->where('period_start', $w['period_start'])
            ->where('period_end', $w['period_end'])
            ->selectRaw('concept, type, SUM(amount) AS total, COUNT(*) AS n')
            ->groupBy('concept', 'type')
            ->get();

        $credito = round((float) $rows->where('type', 'credit')->sum('total'), 2);
        $debito = round((float) $rows->where('type', 'debit')->sum('total'), 2);

        return [
            'conceptos' => $rows->map(fn ($r) => [
                'concepto' => $r->concept,
                'tipo' => $r->type,
                'subtotal' => round((float) $r->total, 2),
                'n' => (int) $r->n,
            ])->values(),
            'total_credito' => $credito,
            'total_debito' => $debito,
            'neto' => round($credito - $debito, 2),
            // Sin calcular, el libro solo trae bonos y descuentos: el sueldo entra al calcular la semana.
            'calculada' => TalentoLiquidation::where('colaborador_id', $colaboradorId)
                ->where('period_start', $w['period_start'])
                ->where('period_end', $w['period_end'])
                ->exists(),
        ];
    }

    /**
     * Promedio de potencia dBm (evidencias de lectura) de la ventana. Con el interruptor encendido solo
     * cuenta las mediciones del colaborador: las de sus órdenes y las de tareas que tiene asignadas.
     */
    public function dbmPromedio(int $colaboradorId, array $w): ?float
    {
        $propio = (bool) config('unificacion.interruptores.mi_dinero_h2', false);
        $userId = $propio ? TalentoColaborador::where('id', $colaboradorId)->value('user_id') : null;

        $avg = DB::table('talento_work_order_media as m')
            ->join('talento_evidence_types as et', 'et.id', '=', 'm.evidence_type_id')
            ->where(function ($q) use ($colaboradorId, $propio, $userId) {
                $q->whereIn('m.work_order_id', fn ($sub) => $sub->select('id')->from('talento_work_orders')
                    ->where('colaborador_id', $colaboradorId));
                if (! $propio) {
                    $q->orWhereNotNull('m.tarea_id');
                } elseif ($userId) {
                    $q->orWhereIn('m.tarea_id', fn ($sub) => $sub->select('task_id')->from('task_user')
                        ->where('user_id', $userId));
                }
            })
            ->where('et.es_lectura_dbm', true)
            ->whereNotNull('m.potencia_dbm')
            ->whereBetween('m.created_at', [$w['start_instant'], $w['end_instant']])
            ->avg('m.potencia_dbm');

        return $avg === null ? null : (float) $avg;
    }

    /**
     * UNI-T H3: las cuatro etapas del dinero de una semana. El importe de la semana (a pagar) cae en
     * UNA sola: validado (sin liquidar), liquidado (cerrada) o pagado; así la suma cuadra con la
     * liquidación. «Pendiente» son las órdenes terminadas que aún nadie valida: cuenta unidades y un
     * estimado (unidades × valor por unidad) que NO entra en la suma. Interruptor apagado = null.
     */
    public function etapas(int $colaboradorId, array $w): ?array
    {
        if (! config('unificacion.interruptores.mi_dinero_etapas_h3', false)) {
            return null;
        }

        $liq = TalentoLiquidation::where('colaborador_id', $colaboradorId)
            ->where('period_start', $w['period_start'])
            ->where('period_end', $w['period_end'])
            ->whereIn('status', ['closed', 'paid'])
            ->first();

        $ls = app(LiquidationService::class);
        $pago = $liq ? (float) $liq->gross_pay : (float) $ls->pagoDeLaSemana($colaboradorId, $w)['a_pagar'];
        $estado = $liq ? ($liq->status === 'paid' ? 'pagado' : 'liquidado') : 'validado';

        $userId = TalentoColaborador::where('id', $colaboradorId)->value('user_id');
        $wo = \App\Modules\Addons\Talento\Models\TalentoWorkOrder::where('colaborador_id', $colaboradorId)
            ->where('status', 'completed')->where('is_billable', true)->where('points', '>', 0);
        $unidadesPend = (int) $wo->sum('points');
        $ordenesPend = (int) $wo->count();
        if ($userId) {
            $tasks = \App\Models\Task::whereNotNull('talento_type_id')->where('is_billable', true)
                ->where('points', '>', 0)->where('status', 'Done')->whereNull('validated_at')
                ->whereHas('users', fn ($q) => $q->where('users.id', $userId));
            $unidadesPend += (int) $tasks->sum('points');
            $ordenesPend += (int) $tasks->count();
        }
        $valorUnidad = (float) ($ls->breakdown($colaboradorId, $w)['value_per_unit'] ?? 0);

        $monto = fn (string $e) => $estado === $e ? round($pago, 2) : 0.0;

        return [
            'estado_semana' => $estado,
            'pendiente' => [
                'ordenes' => $ordenesPend,
                'unidades' => $unidadesPend,
                'importe_estimado' => round($unidadesPend * $valorUnidad, 2),
            ],
            'validado' => ['importe' => $monto('validado')],
            'liquidado' => ['importe' => $monto('liquidado'), 'liquidacion_id' => $liq?->id],
            'pagado' => [
                'importe' => $monto('pagado'),
                'pagado_el' => $liq?->paid_at?->toDateTimeString(),
                'referencia' => $liq?->payment_reference,
            ],
            'total_semana' => round($pago, 2),
        ];
    }
}
