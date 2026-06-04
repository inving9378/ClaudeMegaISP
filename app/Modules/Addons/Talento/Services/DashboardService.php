<?php

namespace App\Modules\Addons\Talento\Services;

use App\Modules\Addons\Talento\Models\TalentoAttendance;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoCompensationRuleHistory;
use App\Modules\Addons\Talento\Models\TalentoCredential;
use App\Modules\Addons\Talento\Models\TalentoLedgerEntry;
use App\Modules\Addons\Talento\Models\TalentoRouteDeviation;
use App\Modules\Addons\Talento\Models\TalentoWorkOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function __construct(
        private FundService             $fundSvc,
        private LoanService             $loanSvc,
        private ProjectActivityService  $projectSvc
    ) {}

    // ── Info cards ────────────────────────────────────────────────────────

    public function infoCards(): array
    {
        $today = now()->toDateString();

        $activeColaboradores = TalentoColaborador::where('status', 'active')->count();

        $checkedInToday = TalentoAttendance::whereDate('check_in_at', $today)
            ->whereNull('check_out_at')
            ->count();

        $ordersToday = TalentoWorkOrder::whereDate('created_at', $today)->count();

        // Alertas: credenciales expiring/expired
        $credAlerts = TalentoCredential::whereIn('status', ['expiring', 'expired'])->count();

        // Alertas: desvíos sin notificar
        $desvioAlerts = 0;
        if (class_exists(TalentoRouteDeviation::class)) {
            $desvioAlerts = TalentoRouteDeviation::where('supervisor_notified', false)->count();
        }

        return [
            'active_colaboradores' => $activeColaboradores,
            'checked_in_today'     => $checkedInToday,
            'orders_today'         => $ordersToday,
            'alerts'               => [
                'credentials' => $credAlerts,
                'deviations'  => $desvioAlerts,
                'total'       => $credAlerts + $desvioAlerts,
            ],
        ];
    }

    // ── Producción diaria (series para ApexCharts) ────────────────────────

    /**
     * Daily production for the last $days days.
     * Returns three series: instalaciones, garantias_billable (>6m), garantias_non_billable (<6m)
     */
    public function dailyProduction(int $days = 30, ?int $colaboradorId = null): array
    {
        $from = now()->subDays($days - 1)->startOfDay();
        $to   = now()->endOfDay();

        $q = TalentoWorkOrder::query()
            ->whereIn('status', ['validated'])
            ->whereBetween('validated_at', [$from, $to])
            ->join('talento_work_order_types as t', 't.id', '=', 'talento_work_orders.type_id')
            ->select(
                DB::raw('DATE(validated_at) as day'),
                't.category',
                't.name as type_name',
                'talento_work_orders.is_billable',
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('day', 't.category', 't.name', 'talento_work_orders.is_billable');

        if ($colaboradorId) {
            $q->where('talento_work_orders.colaborador_id', $colaboradorId);
        }

        $rows = $q->get();

        // Build date range
        $dates = [];
        for ($d = 0; $d < $days; $d++) {
            $dates[] = now()->subDays($days - 1 - $d)->format('Y-m-d');
        }

        $instalaciones      = array_fill_keys($dates, 0);
        $garantiasBillable  = array_fill_keys($dates, 0);
        $garantiasNonBill   = array_fill_keys($dates, 0);

        foreach ($rows as $row) {
            $d = $row->day;
            if (!isset($instalaciones[$d])) continue;

            if (str_contains(strtolower($row->type_name ?? ''), 'instalación') ||
                str_contains(strtolower($row->type_name ?? ''), 'instalacion')) {
                $instalaciones[$d] += $row->total;
            } elseif (str_contains(strtolower($row->type_name ?? ''), 'garantía') ||
                      str_contains(strtolower($row->type_name ?? ''), 'garantia')) {
                if ($row->is_billable) {
                    $garantiasBillable[$d] += $row->total;
                } else {
                    $garantiasNonBill[$d]  += $row->total;
                }
            }
        }

        return [
            'categories'             => array_values($dates),
            'instalaciones'          => array_values($instalaciones),
            'garantias_billable'     => array_values($garantiasBillable),
            'garantias_non_billable' => array_values($garantiasNonBill),
        ];
    }

    // ── Métricas del técnico (preview sin escribir) ───────────────────────

    public function tecnicoPreview(int $colaboradorId, ?string $periodStart = null, ?string $periodEnd = null): array
    {
        $colaborador = TalentoColaborador::with('level')->findOrFail($colaboradorId);

        // Default: current week (Sat–Fri)
        $start = $periodStart ? Carbon::parse($periodStart) : $this->currentWeekStart();
        $end   = $periodEnd   ? Carbon::parse($periodEnd)   : $start->copy()->addDays(6)->endOfDay();

        $ruleSnapshot = TalentoCompensationRuleHistory::where('colaborador_id', $colaboradorId)
            ->where('assigned_at', '<=', $end)
            ->orderByDesc('assigned_at')
            ->value('data');

        $baseSalary  = $ruleSnapshot ? (float)($ruleSnapshot['base_salary']        ?? 0) : 0.0;
        $weeklyQuota = $ruleSnapshot ? (int)($ruleSnapshot['weekly_quota_units']   ?? 0) : 0;

        $internalUnits = (int) TalentoWorkOrder::where('colaborador_id', $colaboradorId)
            ->validatedBillable()
            ->whereBetween('validated_at', [$start->startOfDay(), $end->endOfDay()])
            ->sum('points');

        $externalPoints = $this->projectSvc->pointsForColaboradorInPeriod(
            $colaboradorId,
            $start->toDateString(),
            $end->toDateString()
        );

        $totalUnits = (int) round($internalUnits + $externalPoints);

        $periodDays = $start->diffInDays($end) + 1;
        $absentDays = TalentoAttendance::where('colaborador_id', $colaboradorId)
            ->where('day_type', 'absent')
            ->forPeriod($start->startOfDay(), $end->endOfDay())
            ->count();

        $basePaid = $periodDays > 0 && $absentDays > 0
            ? max(0.0, round($baseSalary * (1 - ($absentDays / $periodDays)), 2))
            : $baseSalary;

        $overproductionPaid = 0.0;
        $valuePerUnit = 0.0;
        if ($weeklyQuota > 0 && $totalUnits > $weeklyQuota) {
            $valuePerUnit       = $baseSalary / $weeklyQuota;
            $overproductionPaid = round(($totalUnits - $weeklyQuota) * $valuePerUnit, 2);
        }

        // Existing ledger entries (bonos, penalizaciones, etc.)
        $otherCredits = (float) TalentoLedgerEntry::where('colaborador_id', $colaboradorId)
            ->whereBetween(DB::raw('period_start'), [$start->toDateString(), $end->toDateString()])
            ->where('type', 'credit')
            ->whereNotIn('concept', ['salary_base', 'overproduction'])
            ->sum('amount');

        $otherDebits = (float) TalentoLedgerEntry::where('colaborador_id', $colaboradorId)
            ->whereBetween(DB::raw('period_start'), [$start->toDateString(), $end->toDateString()])
            ->where('type', 'debit')
            ->whereNotIn('concept', ['salary_base', 'overproduction'])
            ->sum('amount');

        $fundTotal = array_sum($this->fundSvc->deductionsForPeriod($colaboradorId, $start, $end));
        $loanTotal = array_sum($this->loanSvc->deductionsForPeriod($colaboradorId));

        $grossPay = round($basePaid + $overproductionPaid + $otherCredits - $otherDebits - $fundTotal - $loanTotal, 2);

        // Recent WOs for the technician this period
        $recentOrders = TalentoWorkOrder::with('type')
            ->where('colaborador_id', $colaboradorId)
            ->whereBetween('created_at', [$start->startOfDay(), $end->endOfDay()])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'type_id', 'status', 'points', 'is_billable', 'validated_at', 'created_at']);

        return [
            'colaborador_id'   => $colaboradorId,
            'period_start'     => $start->toDateString(),
            'period_end'       => $end->toDateString(),
            'weekly_quota'     => $weeklyQuota,
            'total_units'      => $totalUnits,
            'internal_units'   => $internalUnits,
            'external_points'  => round($externalPoints, 2),
            'quota_pct'        => $weeklyQuota > 0 ? round(($totalUnits / $weeklyQuota) * 100, 1) : null,
            'base_salary'      => $baseSalary,
            'absent_days'      => $absentDays,
            'desglose'         => [
                'base_paid'          => round($basePaid, 2),
                'overproduction'     => round($overproductionPaid, 2),
                'other_credits'      => round($otherCredits, 2),
                'other_debits'       => round($otherDebits, 2),
                'fund_deductions'    => round($fundTotal, 2),
                'loan_deductions'    => round($loanTotal, 2),
            ],
            'gross_pay'        => $grossPay,
            'level'            => $colaborador->level ? ['name' => $colaborador->level->name, 'rank' => $colaborador->level->rank] : null,
            'recent_orders'    => $recentOrders,
        ];
    }

    // ── Sub-paso 2: Calculadora de pago (simulador) ───────────────────────

    /**
     * Simulate pay for hypothetical production units.
     * Reads the collaborator's compensation rule but writes NOTHING.
     */
    public function simulatePay(int $colaboradorId, int $hypotheticalUnits): array
    {
        $ruleSnapshot = TalentoCompensationRuleHistory::where('colaborador_id', $colaboradorId)
            ->orderByDesc('assigned_at')
            ->value('data');

        $baseSalary  = $ruleSnapshot ? (float)($ruleSnapshot['base_salary']        ?? 0) : 0.0;
        $weeklyQuota = $ruleSnapshot ? (int)($ruleSnapshot['weekly_quota_units']   ?? 0) : 0;

        $overproductionPaid = 0.0;
        $valuePerUnit       = $weeklyQuota > 0 ? round($baseSalary / $weeklyQuota, 4) : 0.0;

        if ($weeklyQuota > 0 && $hypotheticalUnits > $weeklyQuota) {
            $overproductionPaid = round(($hypotheticalUnits - $weeklyQuota) * $valuePerUnit, 2);
        }

        $scenarios = [];
        $steps = [0, (int)($weeklyQuota * 0.5), $weeklyQuota, (int)($weeklyQuota * 1.25), (int)($weeklyQuota * 1.5), (int)($weeklyQuota * 2)];
        foreach (array_unique($steps) as $u) {
            if ($u < 0) continue;
            $over = $weeklyQuota > 0 && $u > $weeklyQuota
                ? round(($u - $weeklyQuota) * $valuePerUnit, 2) : 0.0;
            $scenarios[] = ['units' => $u, 'gross' => round($baseSalary + $over, 2)];
        }

        return [
            'colaborador_id'      => $colaboradorId,
            'hypothetical_units'  => $hypotheticalUnits,
            'base_salary'         => $baseSalary,
            'weekly_quota'        => $weeklyQuota,
            'value_per_unit'      => $valuePerUnit,
            'overproduction'      => round($overproductionPaid, 2),
            'gross_pay_simulated' => round($baseSalary + $overproductionPaid, 2),
            'scenarios'           => $scenarios,
        ];
    }

    // ── Equipo del supervisor ─────────────────────────────────────────────

    public function equipoPreview(int $supervisorColaboradorId): array
    {
        $colaborador = TalentoColaborador::findOrFail($supervisorColaboradorId);

        $equipo = TalentoColaborador::with(['user', 'level'])
            ->where('supervisor_id', $colaborador->id)
            ->where('status', 'active')
            ->get();

        $start = $this->currentWeekStart();
        $end   = $start->copy()->addDays(6)->endOfDay();

        $members = $equipo->map(function ($member) use ($start, $end) {
            // Lightweight: only units + pay, no full desglose
            $ruleSnapshot = TalentoCompensationRuleHistory::where('colaborador_id', $member->id)
                ->where('assigned_at', '<=', $end)
                ->orderByDesc('assigned_at')
                ->value('data');

            $baseSalary  = $ruleSnapshot ? (float)($ruleSnapshot['base_salary']        ?? 0) : 0.0;
            $weeklyQuota = $ruleSnapshot ? (int)($ruleSnapshot['weekly_quota_units']   ?? 0) : 0;

            $units = (int) TalentoWorkOrder::where('colaborador_id', $member->id)
                ->validatedBillable()
                ->whereBetween('validated_at', [$start->startOfDay(), $end->endOfDay()])
                ->sum('points');

            $quotaPct = $weeklyQuota > 0 ? round(($units / $weeklyQuota) * 100, 1) : null;

            $over = $weeklyQuota > 0 && $units > $weeklyQuota
                ? round(($units - $weeklyQuota) * ($baseSalary / $weeklyQuota), 2) : 0.0;

            return [
                'id'           => $member->id,
                'name'         => $member->user?->name ?? '—',
                'level'        => $member->level?->name,
                'units'        => $units,
                'quota'        => $weeklyQuota,
                'quota_pct'    => $quotaPct,
                'gross_pay'    => round($baseSalary + $over, 2),
            ];
        });

        return [
            'supervisor_id'  => $supervisorColaboradorId,
            'period_start'   => $start->toDateString(),
            'period_end'     => $end->toDateString(),
            'team_size'      => $equipo->count(),
            'members'        => $members->values(),
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function currentWeekStart(): Carbon
    {
        // Saturday–Friday cycle (same as LiquidationService convention)
        $now = Carbon::now();
        $dayOfWeek = $now->dayOfWeek; // 0=Sun, 6=Sat
        $daysBack  = ($dayOfWeek >= 6) ? 0 : $dayOfWeek + 1;
        return $now->copy()->subDays($daysBack)->startOfDay();
    }
}
