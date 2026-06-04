<?php

namespace App\Modules\Addons\Talento\Services;

use App\Modules\Addons\Talento\Models\TalentoFund;
use App\Modules\Addons\Talento\Models\TalentoLedgerEntry;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class FundService
{
    /**
     * Calcular cuánto apartar de fondos en este corte.
     * Solo fondos accumulating + authorized. Nunca supera lo restante hasta target.
     * Retorna un array: [fund_id => deduction_amount]
     */
    public function deductionsForPeriod(int $colaboradorId, Carbon $periodStart, Carbon $periodEnd): array
    {
        $funds = TalentoFund::where('colaborador_id', $colaboradorId)
            ->where('status', 'accumulating')
            ->where('authorized', true)
            ->get();

        $deductions = [];
        foreach ($funds as $fund) {
            $remaining = (float)$fund->target_amount - (float)$fund->accumulated;
            if ($remaining <= 0) {
                $fund->update(['status' => 'ready']);
                continue;
            }
            $toDeduct = min((float)$fund->weekly_deduction, $remaining);
            if ($toDeduct > 0) {
                $deductions[$fund->id] = round($toDeduct, 2);
            }
        }
        return $deductions;
    }

    /**
     * Escribir asientos fund_contribution en el ledger e incrementar accumulated.
     * Idempotente: borra y recrea los asientos del período para cada fondo.
     * PRECONDICIÓN: llamar dentro de una DB::transaction.
     */
    public function applyDeductions(
        int $colaboradorId,
        array $deductions, // [fund_id => amount]
        Carbon $periodStart,
        Carbon $periodEnd,
        ?int $liquidationId,
        ?int $createdBy
    ): float {
        $total = 0.0;

        foreach ($deductions as $fundId => $amount) {
            $fund = TalentoFund::find($fundId);
            if (!$fund || $fund->status !== 'accumulating' || !$fund->authorized) continue;

            // Idempotente: eliminar asiento previo de este fondo en este período
            TalentoLedgerEntry::where('colaborador_id', $colaboradorId)
                ->where('concept', 'fund_contribution')
                ->where('reference_type', TalentoFund::class)
                ->where('reference_id', $fundId)
                ->where('period_start', $periodStart->toDateString())
                ->where('period_end', $periodEnd->toDateString())
                ->delete();

            TalentoLedgerEntry::create([
                'colaborador_id' => $colaboradorId,
                'type'           => 'debit',
                'concept'        => 'fund_contribution',
                'amount'         => $amount,
                'reference_type' => TalentoFund::class,
                'reference_id'   => $fundId,
                'period_start'   => $periodStart->toDateString(),
                'period_end'     => $periodEnd->toDateString(),
                'notes'          => "Aportación fondo {$fund->purpose} (ahorro {$fund->colaborador_id})",
                'created_by'     => $createdBy,
            ]);

            // Incrementar accumulated — si llega a target → ready
            $newAccum = round((float)$fund->accumulated + $amount, 2);
            $newStatus = $newAccum >= (float)$fund->target_amount ? 'ready' : 'accumulating';
            $fund->update(['accumulated' => $newAccum, 'status' => $newStatus]);

            $total += $amount;
        }

        return round($total, 2);
    }

    /**
     * Autorizar un fondo (requiere firma/confirmación admin).
     */
    public function authorize(TalentoFund $fund, int $authorizedBy): TalentoFund
    {
        $fund->update([
            'authorized'    => true,
            'authorized_at' => now(),
            'authorized_by' => $authorizedBy,
        ]);
        return $fund->fresh();
    }

    /**
     * Marcar fondo como spent (dinero usado para licencia).
     */
    public function markSpent(TalentoFund $fund): TalentoFund
    {
        $fund->update(['status' => 'spent']);
        return $fund->fresh();
    }
}
