<?php

namespace App\Modules\Addons\Talento\Services;

use App\Modules\Addons\Talento\Models\TalentoLedgerEntry;
use App\Modules\Addons\Talento\Models\TalentoLoan;
use Carbon\Carbon;

class LoanService
{
    /**
     * Calcular cuánto descontar de préstamos activos + autorizados en el corte.
     * Nunca supera el balance pendiente de cada préstamo.
     * Retorna [loan_id => deduction_amount]
     */
    public function deductionsForPeriod(int $colaboradorId): array
    {
        $loans = TalentoLoan::where('colaborador_id', $colaboradorId)
            ->where('status', 'active')
            ->where('authorized', true)
            ->whereNotNull('repayment_weekly')
            ->where('balance', '>', 0)
            ->get();

        $deductions = [];
        foreach ($loans as $loan) {
            $toDeduct = min((float)$loan->repayment_weekly, (float)$loan->balance);
            if ($toDeduct > 0) {
                $deductions[$loan->id] = round($toDeduct, 2);
            }
        }
        return $deductions;
    }

    /**
     * Escribir asientos loan_repayment y reducir balance.
     * Idempotente: borra y recrea los asientos del período.
     * PRECONDICIÓN: llamar dentro de una DB::transaction.
     */
    public function applyDeductions(
        int $colaboradorId,
        array $deductions,
        Carbon $periodStart,
        Carbon $periodEnd,
        ?int $createdBy
    ): float {
        $total = 0.0;

        foreach ($deductions as $loanId => $amount) {
            $loan = TalentoLoan::find($loanId);
            if (!$loan || $loan->status !== 'active' || !$loan->authorized) continue;

            // Idempotente
            TalentoLedgerEntry::where('colaborador_id', $colaboradorId)
                ->where('concept', 'loan_repayment')
                ->where('reference_type', TalentoLoan::class)
                ->where('reference_id', $loanId)
                ->where('period_start', $periodStart->toDateString())
                ->where('period_end', $periodEnd->toDateString())
                ->delete();

            TalentoLedgerEntry::registrar([
                'colaborador_id' => $colaboradorId,
                'type'           => 'debit',
                'concept'        => 'loan_repayment',
                'amount'         => $amount,
                'reference_type' => TalentoLoan::class,
                'reference_id'   => $loanId,
                'period_start'   => $periodStart->toDateString(),
                'period_end'     => $periodEnd->toDateString(),
                'notes'          => "Abono préstamo #{$loanId}",
                'created_by'     => $createdBy,
            ]);

            $newBalance = round(max(0, (float)$loan->balance - $amount), 2);
            $loan->update([
                'balance' => $newBalance,
                'status'  => $newBalance <= 0 ? 'paid' : 'active',
            ]);

            $total += $amount;
        }

        return round($total, 2);
    }

    /**
     * Deshace los abonos que un cálculo anterior de la liquidación aplicó en este período
     * (2026-10-23): al recalcular, el préstamo se volvía a cobrar y el asiento viejo se sumaba al
     * nuevo en el monto a pagar.
     */
    public function revertirDeducciones(int $colaboradorId, Carbon $periodStart, Carbon $periodEnd): void
    {
        $asientos = TalentoLedgerEntry::where('colaborador_id', $colaboradorId)
            ->where('concept', 'loan_repayment')
            ->where('reference_type', TalentoLoan::class)
            ->where('period_start', $periodStart->toDateString())
            ->where('period_end', $periodEnd->toDateString())
            ->get();

        foreach ($asientos as $asiento) {
            $loan = TalentoLoan::find($asiento->reference_id);
            if ($loan) {
                $balance = round(min((float) $loan->amount, (float) $loan->balance + (float) $asiento->amount), 2);
                $loan->update(['balance' => $balance, 'status' => $balance > 0 ? 'active' : 'paid']);
            }
            $asiento->delete();
        }
    }

    public function authorize(TalentoLoan $loan, int $authorizedBy): TalentoLoan
    {
        $loan->update([
            'authorized'    => true,
            'authorized_at' => now(),
            'authorized_by' => $authorizedBy,
        ]);
        return $loan->fresh();
    }
}
