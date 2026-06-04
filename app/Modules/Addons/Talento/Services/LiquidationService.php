<?php

namespace App\Modules\Addons\Talento\Services;

use App\Modules\Addons\Talento\Models\TalentoAttendance;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Services\ProjectActivityService;
use App\Modules\Addons\Talento\Models\TalentoCompensationRuleHistory;
use App\Modules\Addons\Talento\Models\TalentoFund;
use App\Modules\Addons\Talento\Models\TalentoLedgerEntry;
use App\Modules\Addons\Talento\Models\TalentoLiquidation;
use App\Modules\Addons\Talento\Models\TalentoWorkOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LiquidationService
{
    public function __construct(private ?FundService $fundService = null)
    {
        $this->fundService = $fundService ?? app(FundService::class);
    }
    /**
     * Calculate (or recalculate) a draft liquidation for a collaborator and period.
     * Period defaults to the most recent complete Saturday-to-Saturday week.
     *
     * If a 'draft' liquidation already exists for this period it is overwritten.
     * A 'closed' liquidation cannot be recalculated.
     *
     * Returns the TalentoLiquidation instance.
     */
    public function calculate(int $colaboradorId, Carbon $periodStart, Carbon $periodEnd): TalentoLiquidation
    {
        $colaborador = TalentoColaborador::findOrFail($colaboradorId);

        $existing = TalentoLiquidation::where('colaborador_id', $colaboradorId)
            ->where('period_start', $periodStart->toDateString())
            ->where('period_end',   $periodEnd->toDateString())
            ->first();

        if ($existing && $existing->status === 'closed') {
            throw new \RuntimeException("La liquidación de este período ya está cerrada.");
        }

        // Get the effective rule at period_start (latest snapshot assigned before/on period_start)
        $ruleSnapshot = TalentoCompensationRuleHistory::where('colaborador_id', $colaboradorId)
            ->where('assigned_at', '<=', $periodEnd)
            ->orderBy('assigned_at', 'desc')
            ->value('data');

        $baseSalary       = $ruleSnapshot ? (float)($ruleSnapshot['base_salary'] ?? 0) : 0.0;
        $weeklyQuota      = $ruleSnapshot ? (int)($ruleSnapshot['weekly_quota_units'] ?? 0) : 0;

        // Sum validated, billable, positive-points orders in the period (validated_at in [start, end])
        $internalUnits = (int) TalentoWorkOrder::where('colaborador_id', $colaboradorId)
            ->validatedBillable()
            ->whereBetween('validated_at', [$periodStart->startOfDay(), $periodEnd->endOfDay()])
            ->sum('points');

        // Fase 5a: add points from external project activities in same period (1/N split, report_date in period)
        $externalPoints = app(ProjectActivityService::class)->pointsForColaboradorInPeriod(
            $colaboradorId,
            $periodStart->toDateString(),
            $periodEnd->toDateString()
        );

        // Single quota: internal OT points + external project activity points
        $totalUnits = (int) round($internalUnits + $externalPoints);

        // Attendance impact on base pay (Fase 3)
        // Count calendar working days in period (Mon-Sat default if shift not configured)
        $periodDays    = $periodStart->diffInDays($periodEnd) + 1;
        $absentDays    = TalentoAttendance::where('colaborador_id', $colaboradorId)
            ->where('day_type', 'absent')
            ->forPeriod($periodStart->startOfDay(), $periodEnd->endOfDay())
            ->count();
        // no_work_company days → base is protected (not deducted)
        $baseProration = $periodDays > 0 && $absentDays > 0
            ? max(0.0, round($baseSalary * (1 - ($absentDays / $periodDays)), 2))
            : $baseSalary;

        // Base pay: prorated if absences exist, protected on company-non-work days
        $basePaid = $baseProration;

        // Overproduction: only for units above quota
        $overproductionPaid = 0.0;
        if ($weeklyQuota > 0 && $totalUnits > $weeklyQuota) {
            $valuePerUnit       = $baseSalary / $weeklyQuota;
            $overUnits          = $totalUnits - $weeklyQuota;
            $overproductionPaid = round($overUnits * $valuePerUnit, 2);
        }

        // Other ledger entries for this period (NOT salary_base or overproduction — those we'll write fresh)
        $otherCredits = (float) TalentoLedgerEntry::where('colaborador_id', $colaboradorId)
            ->where('period_start', $periodStart->toDateString())
            ->where('period_end',   $periodEnd->toDateString())
            ->where('type', 'credit')
            ->whereNotIn('concept', ['salary_base', 'overproduction'])
            ->sum('amount');

        $otherDebits = (float) TalentoLedgerEntry::where('colaborador_id', $colaboradorId)
            ->where('period_start', $periodStart->toDateString())
            ->where('period_end',   $periodEnd->toDateString())
            ->where('type', 'debit')
            ->whereNotIn('concept', ['salary_base', 'overproduction'])
            ->sum('amount');

        // Fondos de ahorro (Fase 6b): authorized + accumulating → debit 'fund_contribution'
        // Se pre-calcula aquí para reflejarlo en gross_pay; se escribe dentro de la transacción.
        $fundDeductions = $this->fundService->deductionsForPeriod($colaboradorId, $periodStart, $periodEnd);
        $fundTotal      = array_sum($fundDeductions);

        $grossPay = round($basePaid + $overproductionPaid + $otherCredits - $otherDebits - $fundTotal, 2);

        return DB::transaction(function () use (
            $colaboradorId, $periodStart, $periodEnd, $existing,
            $totalUnits, $basePaid, $overproductionPaid, $otherCredits, $otherDebits, $grossPay,
            $fundDeductions
        ) {
            $liq = TalentoLiquidation::updateOrCreate(
                [
                    'colaborador_id' => $colaboradorId,
                    'period_start'   => $periodStart->toDateString(),
                    'period_end'     => $periodEnd->toDateString(),
                ],
                [
                    'total_units'         => $totalUnits,
                    'base_paid'           => $basePaid,
                    'overproduction_paid' => $overproductionPaid,
                    'other_credits'       => $otherCredits,
                    'other_debits'        => $otherDebits,
                    'gross_pay'           => $grossPay,
                    'status'              => 'draft',
                ]
            );

            // Write/refresh ledger entries for this liquidation (idempotent: delete draft entries first)
            // Only delete entries created BY a liquidation calc (concept salary_base / overproduction)
            TalentoLedgerEntry::where('colaborador_id', $colaboradorId)
                ->where('period_start', $periodStart->toDateString())
                ->where('period_end',   $periodEnd->toDateString())
                ->whereIn('concept', ['salary_base', 'overproduction'])
                ->whereNull('reference_type')
                ->delete();

            if ($basePaid > 0) {
                TalentoLedgerEntry::create([
                    'colaborador_id' => $colaboradorId,
                    'type'           => 'credit',
                    'concept'        => 'salary_base',
                    'amount'         => $basePaid,
                    'reference_type' => TalentoLiquidation::class,
                    'reference_id'   => $liq->id,
                    'period_start'   => $periodStart->toDateString(),
                    'period_end'     => $periodEnd->toDateString(),
                    'notes'          => 'Sueldo base período ' . $periodStart->toDateString(),
                    'created_by'     => auth()->id(),
                ]);
            }

            if ($overproductionPaid > 0) {
                TalentoLedgerEntry::create([
                    'colaborador_id' => $colaboradorId,
                    'type'           => 'credit',
                    'concept'        => 'overproduction',
                    'amount'         => $overproductionPaid,
                    'reference_type' => TalentoLiquidation::class,
                    'reference_id'   => $liq->id,
                    'period_start'   => $periodStart->toDateString(),
                    'period_end'     => $periodEnd->toDateString(),
                    'notes'          => "Sobreproducción: {$totalUnits} unidades",
                    'created_by'     => auth()->id(),
                ]);
            }

            // Fase 6b: apartar aportaciones de fondos de ahorro autorizados
            if (!empty($fundDeductions)) {
                $this->fundService->applyDeductions(
                    $colaboradorId,
                    $fundDeductions,
                    $periodStart,
                    $periodEnd,
                    $liq->id,
                    auth()->id()
                );
            }

            return $liq->fresh();
        });
    }

    /**
     * Close a draft liquidation (irreversible).
     */
    public function close(TalentoLiquidation $liq): TalentoLiquidation
    {
        if ($liq->status === 'closed') {
            throw new \RuntimeException("La liquidación ya está cerrada.");
        }
        $liq->update(['status' => 'closed', 'closed_at' => now()]);
        return $liq->fresh();
    }

    /**
     * Returns the Saturday-to-Friday week boundaries for the most recently completed week.
     */
    public static function lastWeekBounds(): array
    {
        $today    = Carbon::today();
        $saturday = $today->copy()->startOfWeek(Carbon::SATURDAY);
        if ($saturday->greaterThanOrEqualTo($today)) {
            $saturday->subWeek();
        }
        $friday = $saturday->copy()->addDays(6);
        return [$saturday, $friday];
    }
}
