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

    // ── Calidad/garantías del propio técnico (Portal de Colaborador) ──────

    /**
     * Groundwork "vista por rol" (llamada Irving, 2026-10-08) — lo mismo que
     * ya grafica TalentoDashboard.vue (admin) con dailyProduction(), pero
     * resumido para la tarjeta "Mi calidad" del propio técnico. Reusa
     * dailyProduction() tal cual para la serie diaria -- cero lógica de
     * conteo nueva, solo se agregan los totales/porcentaje que la serie no
     * trae ya armados.
     */
    public function calidadTecnico(int $colaboradorId, int $dias = 180): array
    {
        $serie = $this->dailyProduction($dias, $colaboradorId);

        $garantiasBillable    = array_sum($serie['garantias_billable']);
        $garantiasNonBillable = array_sum($serie['garantias_non_billable']);

        $from = now()->subDays($dias - 1)->startOfDay();
        $to   = now()->endOfDay();

        $trabajosAsignados = (int) TalentoWorkOrder::where('colaborador_id', $colaboradorId)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $completados = (int) TalentoWorkOrder::where('colaborador_id', $colaboradorId)
            ->whereIn('status', ['completed', 'validated'])
            ->whereBetween('created_at', [$from, $to])
            ->count();

        // "Sin incidencias" = trabajos completados que NO terminaron en una
        // garantía no pagable (responsabilidad del técnico) dentro del período.
        $sinIncidenciaDen = $completados + $garantiasNonBillable;
        $calidadPct = $sinIncidenciaDen > 0
            ? round(($completados / $sinIncidenciaDen) * 100, 1)
            : null;

        $recentOrders = TalentoWorkOrder::with('type')
            ->where('colaborador_id', $colaboradorId)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'type_id', 'status', 'created_at']);

        return [
            'period_days'            => $dias,
            'trabajos_asignados'     => $trabajosAsignados,
            'completados'            => $completados,
            'garantias_billable'     => $garantiasBillable,
            'garantias_non_billable' => $garantiasNonBillable,
            'calidad_pct'            => $calidadPct,
            'serie'                  => $serie,
            'recent_orders'          => $recentOrders,
        ];
    }

    // ── Progreso del propio Mostrador (Portal de Colaborador, Fase 3) ─────

    /**
     * "Mi progreso" del Mostrador — a diferencia de calidadTecnico()/mis-ventas
     * (que reusan un motor existente), esto SÍ es agregación nueva: pagos que
     * capturó (payments.add_by = user_id, mismo criterio de autoría que ya usa
     * el resto del sistema — ver Payment::user()) y tickets que tiene
     * asignados (tickets.assigned_to = user_id, confirmado contra
     * Ticket::assign() y TicketController::opened()/closed() admin, que usan
     * estado='Nuevo'/'Cerrado'). Self-scoped por $userId — SIEMPRE
     * auth()->id() del colaborador en sesión, nunca un id que llegue del
     * cliente.
     */
    public function progresoMostrador(int $userId, int $dias = 30): array
    {
        $from = now()->subDays($dias - 1)->startOfDay();
        $to   = now()->endOfDay();

        $pagosPeriodo = \App\Models\Payment::where('add_by', $userId)
            ->whereBetween('created_at', [$from, $to]);

        $pagosHoy = (int) \App\Models\Payment::where('add_by', $userId)
            ->whereDate('created_at', now()->toDateString())
            ->count();

        $totalPagosPeriodo = (int) (clone $pagosPeriodo)->count();
        $montoPeriodo      = (float) (clone $pagosPeriodo)->sum('amount');

        $serieRows = (clone $pagosPeriodo)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $dates = [];
        for ($d = 0; $d < $dias; $d++) {
            $dates[] = now()->subDays($dias - 1 - $d)->format('Y-m-d');
        }
        $conteoSerie = array_map(fn ($date) => (int) ($serieRows[$date] ?? 0), $dates);

        // Whitelist explícito de campos: client_main_information (cargada solo para resolver el
        // nombre vía el accessor paymentable_name) trae columnas sensibles (password en claro,
        // email, teléfono, dirección) — NUNCA se serializa la relación completa al frontend.
        $pagosRecientes = \App\Models\Payment::with(['payment_method', 'client_main_information'])
            ->where('add_by', $userId)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'paymentable_id', 'payment_method_id', 'amount', 'created_at'])
            ->map(fn ($p) => [
                'id'               => $p->id,
                'amount'           => $p->amount,
                'created_at'       => $p->created_at,
                'paymentable_name' => $p->paymentable_name,
                'payment_method'   => $p->payment_method?->type,
            ]);

        $ticketsAbiertosCount = (int) \App\Models\Ticket::where('assigned_to', $userId)
            ->where('estado', 'Nuevo')
            ->count();

        $ticketsCerradosPeriodo = (int) \App\Models\Ticket::where('assigned_to', $userId)
            ->where('estado', 'Cerrado')
            ->whereBetween('updated_at', [$from, $to])
            ->count();

        $ticketsAbiertos = \App\Models\Ticket::where('assigned_to', $userId)
            ->where('estado', 'Nuevo')
            ->orderByDesc('date_time')
            ->limit(10)
            ->get(['id', 'topic', 'priority', 'date_time']);

        return [
            'period_days'              => $dias,
            'pagos_hoy'                => $pagosHoy,
            'pagos_periodo'            => $totalPagosPeriodo,
            'monto_periodo'            => $montoPeriodo,
            'tickets_abiertos'         => $ticketsAbiertosCount,
            'tickets_cerrados_periodo' => $ticketsCerradosPeriodo,
            'serie'                    => ['categories' => $dates, 'pagos' => $conteoSerie],
            'pagos_recientes'          => $pagosRecientes,
            'tickets_abiertos_lista'   => $ticketsAbiertos,
        ];
    }

    // ── "Mi equipo" (Portal de Colaborador, Fase 5) — vista agregada para supervisores ────

    /**
     * Fila resumen de cada colaborador del equipo (un número por persona, según su track —
     * técnico/vendedor/mostrador), NO el dashboard completo de cada uno (sería carísimo y
     * ruidoso de listar). $verTodo=true ignora supervisor_id y trae a todos los colaboradores
     * activos (candado de permiso/rol en el controller, no aquí). En dev hoy ningún colaborador
     * tiene supervisor_id poblado — el nivel "mi equipo" saldrá vacío hasta que se asignen
     * supervisores en el organigrama real; es honesto reflejar eso, no inventar datos.
     */
    public function equipoProgreso(int $supervisorColaboradorId, bool $verTodo, int $dias = 30): array
    {
        $query = TalentoColaborador::with('user')->where('status', 'active');
        if ($verTodo) {
            $query->where('id', '!=', $supervisorColaboradorId);
        } else {
            $query->where('supervisor_id', $supervisorColaboradorId);
        }

        $from = now()->subDays($dias - 1)->startOfDay();

        $filas = [];
        foreach ($query->get() as $col) {
            if (! $col->user) {
                continue;
            }

            $track  = \App\Modules\Addons\Talento\Support\Actor::for($col->user)->trackPrincipal();
            $userId = $col->user->id;

            $fila = [
                'colaborador_id' => $col->id,
                'nombre'         => $col->user->name,
                'track'          => $track,
                'metric_label'   => null,
                'metric_value'   => null,
            ];

            if (str_starts_with($track, 'tecnico_')) {
                $fila['metric_label'] = "Completados ({$dias}d)";
                $fila['metric_value'] = (int) TalentoWorkOrder::where('colaborador_id', $col->id)
                    ->whereIn('status', ['completed', 'validated'])
                    ->where('created_at', '>=', $from)
                    ->count();
            } elseif ($track === 'vendedor') {
                // Mismo criterio/columna (varchar, comparación por string Y-m-d) que ya usa
                // StaticsController::salesAndProspects() para su rango por defecto.
                $fila['metric_label'] = "Ventas ({$dias}d)";
                $fila['metric_value'] = (int) \App\Models\ClientMainInformation::where('seller_id', $userId)
                    ->where('activation_date', '>=', $from->format('Y-m-d'))
                    ->count();
            } elseif ($track === 'mostrador') {
                $fila['metric_label'] = "Pagos capturados ({$dias}d)";
                $fila['metric_value'] = (int) \App\Models\Payment::where('add_by', $userId)
                    ->where('created_at', '>=', $from)
                    ->count();
            }

            $filas[] = $fila;
        }

        return ['period_days' => $dias, 'ver_todo' => $verTodo, 'equipo' => $filas];
    }

    // ── Métricas del técnico (preview sin escribir) ───────────────────────

    public function tecnicoPreview(int $colaboradorId, ?string $periodStart = null, ?string $periodEnd = null): array
    {
        $colaborador = TalentoColaborador::with('level')->findOrFail($colaboradorId);

        // Ventana de pago (PayWeek): semana completa alineada al motor (corte 18:00).
        $w           = $periodStart
            ? \App\Modules\Addons\Talento\Support\PayWeek::boundsFor(Carbon::parse($periodStart)->copy()->addDay())
            : \App\Modules\Addons\Talento\Support\PayWeek::current();
        $start       = Carbon::parse($w['period_start']);
        $end         = Carbon::parse($w['period_end']);
        $filterStart = $w['start_instant'];
        $filterEnd   = $w['end_instant'];

        $ruleSnapshot = TalentoCompensationRuleHistory::where('colaborador_id', $colaboradorId)
            ->where('assigned_at', '<=', $end)
            ->orderByDesc('assigned_at')
            ->value('data');

        $baseSalary  = $ruleSnapshot ? (float)($ruleSnapshot['base_salary']        ?? 0) : 0.0;
        $weeklyQuota = $ruleSnapshot ? (int)($ruleSnapshot['weekly_quota_units']   ?? 0) : 0;

        $internalUnits = (int) TalentoWorkOrder::where('colaborador_id', $colaboradorId)
            ->validatedBillable()
            ->whereBetween('validated_at', [$filterStart, $filterEnd])
            ->sum('points');

        $externalPoints = $this->projectSvc->pointsForColaboradorInPeriod(
            $colaboradorId,
            $start->toDateString(),
            $end->toDateString()
        );

        $totalUnits = (int) round($internalUnits + $externalPoints);

        $periodDays = (int) round($filterStart->diffInHours($filterEnd) / 24); // 7 (legacy y nueva)
        $absentDays = TalentoAttendance::where('colaborador_id', $colaboradorId)
            ->where('day_type', 'absent')
            ->forPeriod($filterStart, $filterEnd)
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

        $w           = \App\Modules\Addons\Talento\Support\PayWeek::current();
        $start       = Carbon::parse($w['period_start']);
        $end         = Carbon::parse($w['period_end']);
        $filterStart = $w['start_instant'];
        $filterEnd   = $w['end_instant'];

        $members = $equipo->map(function ($member) use ($start, $end, $filterStart, $filterEnd) {
            // Lightweight: only units + pay, no full desglose
            $ruleSnapshot = TalentoCompensationRuleHistory::where('colaborador_id', $member->id)
                ->where('assigned_at', '<=', $end)
                ->orderByDesc('assigned_at')
                ->value('data');

            $baseSalary  = $ruleSnapshot ? (float)($ruleSnapshot['base_salary']        ?? 0) : 0.0;
            $weeklyQuota = $ruleSnapshot ? (int)($ruleSnapshot['weekly_quota_units']   ?? 0) : 0;

            $units = (int) TalentoWorkOrder::where('colaborador_id', $member->id)
                ->validatedBillable()
                ->whereBetween('validated_at', [$filterStart, $filterEnd])
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

    // currentWeekStart() eliminado: la ventana de la semana ahora la da PayWeek::current()
    // (punto único de verdad, con corte 18:00 y cutover).
}
