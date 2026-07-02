<?php

namespace App\Modules\Addons\Talento\Services;

use App\Models\Task;
use App\Modules\Addons\Talento\Models\TalentoAttendance;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Services\ProjectActivityService;
use App\Modules\Addons\Talento\Models\TalentoCompensationRuleHistory;
use App\Modules\Addons\Talento\Models\TalentoFund;
use App\Modules\Addons\Talento\Models\TalentoLedgerEntry;
use App\Modules\Addons\Talento\Models\TalentoLiquidation;
use App\Modules\Addons\Talento\Support\PayWeek;
use App\Modules\Addons\Talento\Models\TalentoWorkOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LiquidationService
{
    public function __construct(
        private ?FundService $fundService = null,
        private ?LoanService $loanService = null
    ) {
        $this->fundService = $fundService ?? app(FundService::class);
        $this->loanService = $loanService ?? app(LoanService::class);
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

        // Ventana canónica (PayWeek, punto único de verdad). Normaliza el período (sábado de
        // apertura/cierre) y expone los instantes de corte en DATETIME para filtrar validated_at
        // con la HORA (corte 18:00), no por día. Forward-only: para períodos pre-cutover devuelve
        // la ventana legacy Sáb 00:00→Vie 23:59, IDÉNTICA al motor histórico (no-regresión).
        $window      = PayWeek::boundsFor($periodStart->copy()->addDay());
        $periodStart = Carbon::parse($window['period_start']);
        $periodEnd   = Carbon::parse($window['period_end']);
        $filterStart = $window['start_instant'];
        $filterEnd   = $window['end_instant'];

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

        // Unidades facturables — PUNTO ÚNICO DE VERDAD (mismas fuentes que el desglose del portal:
        // OTs + tasks de campo + puntos de proyecto externo). Ver countBillableUnits().
        $totalUnits = $this->countBillableUnits($colaboradorId, $window)['total'];

        // Attendance impact on base pay (Fase 3)
        // Count calendar working days in period (Mon-Sat default if shift not configured)
        $periodDays    = (int) round($filterStart->diffInHours($filterEnd) / 24); // 7 (legacy y nueva)
        $absentDays    = TalentoAttendance::where('colaborador_id', $colaboradorId)
            ->where('day_type', 'absent')
            ->forPeriod($filterStart, $filterEnd)
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
        $fundDeductions = $this->fundService->deductionsForPeriod($colaboradorId, $periodStart, $periodEnd);
        $fundTotal      = array_sum($fundDeductions);

        // Préstamos activos + autorizados (Fase 6c): → debit 'loan_repayment'
        $loanDeductions = $this->loanService->deductionsForPeriod($colaboradorId);
        $loanTotal      = array_sum($loanDeductions);

        $grossPay = round($basePaid + $overproductionPaid + $otherCredits - $otherDebits - $fundTotal - $loanTotal, 2);

        return DB::transaction(function () use (
            $colaboradorId, $periodStart, $periodEnd, $existing,
            $totalUnits, $basePaid, $overproductionPaid, $otherCredits, $otherDebits, $grossPay,
            $fundDeductions, $loanDeductions
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

            // Fase 6c: abonos a préstamos autorizados
            if (!empty($loanDeductions)) {
                $this->loanService->applyDeductions(
                    $colaboradorId,
                    $loanDeductions,
                    $periodStart,
                    $periodEnd,
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
     * Unidades facturables de un colaborador en una ventana PayWeek — PUNTO ÚNICO DE VERDAD de
     * "cuántas unidades cuentan para el pago". Lo llaman calculate() (liquidación) y breakdown()
     * (desglose/portal), así el portal muestra EXACTAMENTE las mismas unidades que se pagan (no
     * pueden divergir por construcción). Fuentes: OTs (talento_work_orders) + tasks de campo
     * (tasks, Capa 4.4) + puntos de proyecto externo (ProjectActivityService, Fase 5a).
     */
    public function countBillableUnits(int $colaboradorId, array $window): array
    {
        $filterStart = $window['start_instant'];
        $filterEnd   = $window['end_instant'];

        // Fuente 1: OTs legacy (talento_work_orders)
        $woUnits = (int) TalentoWorkOrder::where('colaborador_id', $colaboradorId)
            ->validatedBillable()
            ->whereBetween('validated_at', [$filterStart, $filterEnd])
            ->sum('points');

        // Fuente 2: tasks tipo=campo validadas (asignación via task_user pivot)
        $taskUnits  = 0;
        $taskUserId = TalentoColaborador::where('id', $colaboradorId)->value('user_id');
        if ($taskUserId) {
            $taskUnits = (int) Task::where('tipo', 'campo')
                ->whereNotNull('talento_type_id')
                ->where('is_billable', true)
                ->where('points', '>', 0)
                ->where('status', 'Done')
                ->whereNotNull('validated_at')
                ->whereBetween('validated_at', [$filterStart, $filterEnd])
                ->whereHas('users', fn ($q) => $q->where('users.id', $taskUserId))
                ->sum('points');
        }

        // Fuente 3: puntos de actividades de proyecto externo (reparto 1/N, report_date en período)
        $externalPoints = (float) app(ProjectActivityService::class)->pointsForColaboradorInPeriod(
            $colaboradorId,
            $window['period_start'],
            $window['period_end']
        );

        return [
            'wo'       => $woUnits,
            'task'     => $taskUnits,
            'external' => $externalPoints,
            'total'    => (int) round($woUnits + $taskUnits + $externalPoints),
        ];
    }

    /**
     * Desglose (LECTURA, sin escribir) de una ventana PayWeek: unidades, cuota, valor por
     * unidad (base÷cuota), sobreproducción y pago proyectado. Reusado por el 'avance' admin
     * y por el portal técnico self-scoped — NO recalcula nada en el front. Las unidades salen
     * de countBillableUnits() (MISMO conteo que la liquidación). La regla se toma como estaba
     * en el período (assigned_at <= period_end).
     */
    public function breakdown(int $colaboradorId, array $window): array
    {
        $u     = $this->countBillableUnits($colaboradorId, $window);
        $units = $u['total'];

        $ruleData = TalentoCompensationRuleHistory::where('colaborador_id', $colaboradorId)
            ->where('assigned_at', '<=', $window['period_end'] . ' 23:59:59')
            ->orderByDesc('assigned_at')
            ->value('data');

        $quota          = $ruleData ? (int)($ruleData['weekly_quota_units'] ?? 0) : 0;
        $baseSalary     = $ruleData ? (float)($ruleData['base_salary'] ?? 0) : 0.0;
        $valuePerUnit   = $quota > 0 ? round($baseSalary / $quota, 4) : 0.0;
        $overUnits      = max(0, $units - $quota);
        $overproduction = round($overUnits * $valuePerUnit, 2);

        return [
            'period_start'   => $window['period_start'],
            'period_end'     => $window['period_end'],
            'units'          => $units,
            'units_wo'       => $u['wo'],        // desglose de fuentes (mismas que la liquidación)
            'units_task'     => $u['task'],
            'units_external' => $u['external'],
            'quota'          => $quota,
            'base_salary'    => round($baseSalary, 2),
            'value_per_unit' => $valuePerUnit,
            'over_units'     => $overUnits,
            'overproduction' => $overproduction,
            'projected_pay'  => round($baseSalary + $overproduction, 2),
        ];
    }

    public static function lastWeekBounds(): array
    {
        // Delega en PayWeek (punto único de verdad): última semana COMPLETADA, respetando
        // cutover y la ventana legacy. Devuelve [apertura, cierre] como fechas de período.
        $w = PayWeek::lastCompleted();
        return [Carbon::parse($w['period_start']), Carbon::parse($w['period_end'])];
    }
}
