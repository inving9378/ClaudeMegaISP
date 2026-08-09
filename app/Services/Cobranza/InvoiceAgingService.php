<?php

namespace App\Services\Cobranza;

use App\Models\Invoice;

/**
 * Punto único de verdad de aging/buckets de facturas vencidas (item #484, Fase 1).
 * Read-only, sin PII: solo agregados sobre `invoices`. Extraído de
 * CobranzaAdvisorService::metricas() para que el futuro armado de campañas
 * (F2) y el asesor de IA compartan el mismo cálculo — no dos versiones.
 */
class InvoiceAgingService
{
    /** Rangos de antigüedad en días desde el vencimiento: [desde, hasta|null]. */
    public const RANGOS = [[0, 30], [31, 60], [61, 90], [91, null]];

    /** Agregados de facturación pendiente: totales + buckets de antigüedad. */
    public function metricas(): array
    {
        $pendientes = Invoice::query()->where('status', '!=', 'paid');

        $totalPendientes = (clone $pendientes)->count();
        $vencidas = (clone $pendientes)->where('due_date', '<', now());
        $totalVencidas = (clone $vencidas)->count();
        $montoVencido = (float) (clone $vencidas)->sum('pending_balance');
        $clientesConSaldoVencido = (clone $vencidas)->distinct('client_id')->count('client_id');

        return [
            'total_facturas_pendientes'  => $totalPendientes,
            'total_facturas_vencidas'    => $totalVencidas,
            'monto_total_vencido'        => round($montoVencido, 2),
            'clientes_con_saldo_vencido' => $clientesConSaldoVencido,
            'buckets_antiguedad'         => $this->buckets($pendientes),
            'generado_at'                => now()->toDateTimeString(),
        ];
    }

    /** Facturas + monto por bucket de antigüedad (0-30/31-60/61-90/91+ días). */
    public function buckets(?\Illuminate\Database\Eloquent\Builder $pendientes = null): array
    {
        $pendientes ??= Invoice::query()->where('status', '!=', 'paid');

        $buckets = [];
        foreach (self::RANGOS as [$desde, $hasta]) {
            $q = (clone $pendientes)->where('due_date', '<', now()->subDays($desde));
            if ($hasta !== null) {
                $q->where('due_date', '>=', now()->subDays($hasta));
            }
            $etiqueta = $hasta !== null ? "{$desde}-{$hasta} días" : "{$desde}+ días";
            $buckets[$etiqueta] = [
                'facturas' => (clone $q)->count(),
                'monto'    => (float) (clone $q)->sum('pending_balance'),
            ];
        }

        return $buckets;
    }

    /**
     * Facturas vencidas dentro de una ventana de días de antigüedad (para el
     * futuro armado de batch en F2). Read-only; no selecciona ni envía nada.
     */
    public function facturasEnRango(int $diasDesde, ?int $diasHasta = null)
    {
        $q = Invoice::query()
            ->where('status', '!=', 'paid')
            ->where('due_date', '<', now()->subDays($diasDesde));

        if ($diasHasta !== null) {
            $q->where('due_date', '>=', now()->subDays($diasHasta));
        }

        return $q;
    }
}
