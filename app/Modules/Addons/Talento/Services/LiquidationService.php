<?php

namespace App\Modules\Addons\Talento\Services;

use App\Models\Task;
use App\Modules\Addons\Talento\Models\TalentoCompensationRule;
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
        // En una sola transacción: deshacer lo que aplicó un cálculo anterior, calcular y guardar.
        return DB::transaction(fn () => $this->calcular($colaboradorId, $periodStart, $periodEnd));
    }

    /**
     * La liquidación de la semana tal como la dejaría «Calcular» ahora, sin guardar nada
     * (2026-10-23): corre el mismo cálculo dentro de una transacción que se deshace, así el monto
     * es exactamente el que pagaría el botón. Para el Mostrador, que solo ve cuánto cobra cada uno.
     */
    public function simular(int $colaboradorId, Carbon $periodStart, Carbon $periodEnd): TalentoLiquidation
    {
        DB::beginTransaction();
        try {
            $liq = $this->calculate($colaboradorId, $periodStart, $periodEnd);
        } finally {
            DB::rollBack();
        }
        $liq->setAttribute('id', null);
        $liq->exists = false;

        return $liq;
    }

    private function calcular(int $colaboradorId, Carbon $periodStart, Carbon $periodEnd): TalentoLiquidation
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

        // Recalcular (2026-10-23): se deshacen primero los abonos a préstamos y las aportaciones a
        // fondos que un cálculo anterior aplicó en esta semana. Sin esto, cada recálculo volvía a
        // cobrar el préstamo y a sumar el fondo, y el monto a pagar restaba el asiento viejo además
        // del nuevo.
        $this->loanService->revertirDeducciones($colaboradorId, $periodStart, $periodEnd);
        $this->fundService->revertirDeducciones($colaboradorId, $periodStart, $periodEnd);

        // Get the effective rule at period_start (latest snapshot assigned before/on period_start)
        $ruleSnapshot = TalentoCompensationRuleHistory::where('colaborador_id', $colaboradorId)
            ->where('assigned_at', '<=', $periodEnd)
            ->orderBy('assigned_at', 'desc')
            ->value('data');

        $baseSalary       = $ruleSnapshot ? (float)($ruleSnapshot['base_salary'] ?? 0) : 0.0;
        $weeklyQuota      = $ruleSnapshot ? TalentoCompensationRule::cuotaSemanal($ruleSnapshot) : 0;

        // Unidades facturables — PUNTO ÚNICO DE VERDAD (mismas fuentes que el desglose del portal:
        // OTs + órdenes de Talento de campo o internas + puntos de proyecto externo). Ver countBillableUnits().
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
        // UNI-T I5: con el interruptor apagado (default) siempre aplica, como hoy.
        $compat = app(\App\Services\IncentiveCompatibilityService::class);
        $sobreproduccionAplica = $compat->aplicable(
            'talento',
            $compat->otorgadosPrevios('talento', $colaborador->user_id, $periodStart->toDateString(), $periodEnd->toDateString())
        );
        if ($sobreproduccionAplica && $weeklyQuota > 0 && $totalUnits > $weeklyQuota) {
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
            // Only delete entries created BY un cálculo de liquidación (concept
            // salary_base / overproduction, SIEMPRE exclusivos de este método).
            // ⚠️ Bug real encontrado 2026-10-14 (verificado: 2 filas duplicadas de
            // salary_base para un mismo colaborador+período, mismo monto, mismo
            // reference_id): el filtro traía ->whereNull('reference_type'), pero
            // las filas que ESTE MISMO bloque escribe (abajo) llevan
            // reference_type=TalentoLiquidation::class — nunca null. Cada
            // recálculo de una liquidación YA EXISTENTE (botón "Calcular" dos
            // veces, o cualquier corrección) dejaba viva la fila anterior y
            // sumaba una nueva, acumulando duplicados silenciosos en el ledger
            // (no afectaba el monto de gross_pay, que se recalcula fresco cada
            // vez, pero sí ensuciaba el detalle "Ledger del período" que ve el
            // colaborador). El match concept+colaborador+período ya es lo
            // bastante preciso sin necesitar whereNull('reference_type').
            TalentoLedgerEntry::where('colaborador_id', $colaboradorId)
                ->where('period_start', $periodStart->toDateString())
                ->where('period_end',   $periodEnd->toDateString())
                ->whereIn('concept', ['salary_base', 'overproduction'])
                ->delete();

            if ($basePaid > 0) {
                TalentoLedgerEntry::registrar([
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
                TalentoLedgerEntry::registrar([
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

        // J3: con el interruptor encendido, el cierre recalcula y suma los ajustes pendientes.
        if (config('unificacion.ajustes_semana_cerrada.activo', false)) {
            $fresh = $this->calculate($liq->colaborador_id, $liq->period_start, $liq->period_end);
            $ajustes = $this->ajustesPendientes($fresh);
            $fresh->update(['gross_pay' => round((float) $fresh->gross_pay + (float) $ajustes->sum('amount_delta'), 2)]);
            $liq = $fresh;
        }

        $liq->update(['status' => 'closed', 'closed_at' => now()]);
        return $liq->fresh();
    }

    /**
     * J3: ajustes que caen en la semana de esta liquidación (traen referencia al original).
     * Solo lectura; con el interruptor apagado siempre vacío.
     */
    public function ajustesPendientes(TalentoLiquidation $liq)
    {
        if (!config('unificacion.ajustes_semana_cerrada.activo', false)) {
            return collect();
        }

        return \App\Modules\Addons\Talento\Models\TalentoPeriodAdjustment::where('colaborador_id', $liq->colaborador_id)
            ->where('target_period_start', $liq->period_start->toDateString())
            ->where('target_period_end', $liq->period_end->toDateString())
            ->orderBy('id')
            ->get();
    }

    /**
     * J3: previsualización del cierre — recalcula sin guardar y compara con lo guardado.
     * Devuelve las diferencias por campo y los ajustes que se incorporarían.
     */
    public function previewClose(TalentoLiquidation $liq): array
    {
        if ($liq->status === 'closed') {
            throw new \RuntimeException("La liquidación ya está cerrada.");
        }

        $sim = $this->simular($liq->colaborador_id, $liq->period_start, $liq->period_end);
        $ajustes = $this->ajustesPendientes($liq);
        $delta = (float) $ajustes->sum('amount_delta');

        $diferencias = [];
        foreach (['total_units', 'base_paid', 'overproduction_paid', 'other_credits', 'other_debits', 'gross_pay'] as $campo) {
            $antes = (float) $liq->{$campo};
            $despues = (float) $sim->{$campo} + ($campo === 'gross_pay' ? $delta : 0);
            if (round($antes, 2) !== round($despues, 2)) {
                $diferencias[$campo] = ['guardado' => $antes, 'recalculado' => round($despues, 2)];
            }
        }

        return [
            'diferencias'      => $diferencias,
            'ajustes'          => $ajustes->values(),
            'ajustes_total'    => round($delta, 2),
            'gross_pay_final'  => round((float) $sim->gross_pay + $delta, 2),
        ];
    }

    /**
     * J3: ¿el instante cae en una semana de pago YA cerrada para el colaborador? (liquidación `closed`
     * con el period_start/period_end de la ventana PayWeek que contiene al instante). Solo detecta:
     * ninguna escritura lo consulta todavía. Con el interruptor apagado siempre devuelve false.
     */
    public function isInClosedWeek(int $colaboradorId, \Carbon\Carbon $instant): bool
    {
        if (!config('unificacion.ajustes_semana_cerrada.activo', false)) {
            return false;
        }

        $w = \App\Modules\Addons\Talento\Support\PayWeek::boundsFor($instant->copy());

        return TalentoLiquidation::where('colaborador_id', $colaboradorId)
            ->where('period_start', $w['period_start'])
            ->where('period_end', $w['period_end'])
            ->where('status', 'closed')
            ->exists();
    }

    /**
     * J3 Fase 2: instante efectivo para una escritura que afecta unidades pagables (validated_at).
     * Si $instant cae en una semana ya cerrada, NO se toca esa semana: se devuelve el arranque de la
     * siguiente semana abierta (donde countBillableUnits ya lo contará) y se deja un ajuste en
     * talento_period_adjustments con referencia al original. Interruptor apagado o semana abierta:
     * devuelve $instant sin tocar nada. Nunca lanza: ante un fallo conserva el instante original.
     */
    public function instantForLateWrite(int $colaboradorId, \Carbon\Carbon $instant, string $originalType, int $originalId, float $points = 0, ?int $userId = null): \Carbon\Carbon
    {
        try {
            if (!$this->isInClosedWeek($colaboradorId, $instant)) {
                return $instant;
            }

            $orig = \App\Modules\Addons\Talento\Support\PayWeek::boundsFor($instant->copy());
            $target = $orig;
            for ($i = 0; $i < 6; $i++) {
                $target = \App\Modules\Addons\Talento\Support\PayWeek::boundsFor($target['end_instant']->copy()->addSecond());
                if (!$this->isInClosedWeek($colaboradorId, $target['start_instant'])) {
                    break;
                }
            }

            \Illuminate\Support\Facades\DB::table('talento_period_adjustments')->insert([
                'colaborador_id'        => $colaboradorId,
                'original_type'         => $originalType,
                'original_id'           => $originalId,
                'original_period_start' => $orig['period_start'],
                'original_period_end'   => $orig['period_end'],
                'target_period_start'   => $target['period_start'],
                'target_period_end'     => $target['period_end'],
                'points_delta'          => $points,
                'amount_delta'          => 0,
                'reason'                => 'Escritura tardía en semana cerrada: pasa a la siguiente semana abierta',
                'created_by'            => $userId,
                'created_at'            => now(),
                'updated_at'            => now(),
            ]);

            return $target['start_instant']->copy();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('J3 escritura tardía: se conserva el instante original', ['err' => $e->getMessage()]);
            return $instant;
        }
    }

    /**
     * Unidades facturables de un colaborador en una ventana PayWeek — PUNTO ÚNICO DE VERDAD de
     * "cuántas unidades cuentan para el pago". Lo llaman calculate() (liquidación) y breakdown()
     * (desglose/portal), así el portal muestra EXACTAMENTE las mismas unidades que se pagan (no
     * pueden divergir por construcción). Fuentes: OTs (talento_work_orders) + órdenes de Talento
     * en tasks, de campo o internas (Capa 4.4) + puntos de proyecto externo (ProjectActivityService,
     * Fase 5a).
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
        // UNI-T L3b: la reparación de la PROPIA instalación dentro de la ventana no suma (interruptor, apagado = 0).
        $woUnits = max(0, $woUnits - app(GarantiaPropiaService::class)->puntosExcluidosWo($colaboradorId, $window));

        // Fuente 2: órdenes de Talento en tasks, validadas (asignación via task_user pivot). Cuentan
        // las de campo y las internas por igual (2026-10-26: el técnico de casa y el de calle cobran
        // de la misma forma); lo que decide es que el tipo tenga puntos y sea cobrable.
        $taskUnits  = 0;
        $taskUserId = TalentoColaborador::where('id', $colaboradorId)->value('user_id');
        if ($taskUserId) {
            $tasksQ = Task::whereNotNull('talento_type_id')
                ->where('is_billable', true)
                ->where('points', '>', 0)
                ->where('status', 'Done')
                ->whereNotNull('validated_at')
                ->whereBetween('validated_at', [$filterStart, $filterEnd])
                ->whereHas('users', fn ($q) => $q->where('users.id', $taskUserId));
            $porRol = app(ReconocimientoPorRolService::class);
            if ($porRol->activo()) {
                // UNI-T D8b: cada orden cuenta según el rol del colaborador (responsable/participante).
                $taskUnits = (int) round($tasksQ->get(['id', 'points'])
                    ->sum(fn ($t) => $t->points * $porRol->factor('task', (int) $t->id, (int) $taskUserId)));
            } else {
                $taskUnits = (int) $tasksQ->sum('points');
            }
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

        $quota          = $ruleData ? TalentoCompensationRule::cuotaSemanal($ruleData) : 0;
        $baseSalary     = $ruleData ? (float)($ruleData['base_salary'] ?? 0) : 0.0;
        $valuePerUnit   = $quota > 0 ? round($baseSalary / $quota, 4) : 0.0;
        $overUnits      = max(0, $units - $quota);
        // Con el valor sin redondear, igual que calculate(): redondear antes de multiplicar
        // dejaba el desglose un centavo arriba del pago.
        $overproduction = $quota > 0 ? round($overUnits * ($baseSalary / $quota), 2) : 0.0;

        // 2026-10-28: el pago proyectado era sueldo base + sobreproducción y no restaba faltas,
        // penalizaciones, préstamos ni fondo, ni sumaba bonos: el técnico veía $2,000 donde el
        // Mostrador veía $1,800. Ahora es lo que pagaría «Calcular».
        $pago = $this->pagoDeLaSemana($colaboradorId, $window);

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
            'projected_pay'  => $pago['a_pagar'],
            'pago'           => $pago,
        ];
    }

    /**
     * Lo que se le paga a la persona esa semana con el mismo cálculo que «Calcular»: sueldo base
     * con faltas, sobreproducción, bonos, penalizaciones, préstamos y fondo de ahorro. Si la
     * semana ya se cerró, lo que se cerró. Sin guardar nada (ver simular()).
     */
    public function pagoDeLaSemana(int $colaboradorId, array $window): array
    {
        $liq = TalentoLiquidation::where('colaborador_id', $colaboradorId)
            ->where('period_start', $window['period_start'])
            ->where('period_end', $window['period_end'])
            ->where('status', 'closed')
            ->first()
            ?? $this->simular($colaboradorId, Carbon::parse($window['period_start']), Carbon::parse($window['period_end']));

        $base       = (float) $liq->base_paid;
        $sobre      = (float) $liq->overproduction_paid;
        $bonos      = (float) $liq->other_credits;
        $descuentos = (float) $liq->other_debits;
        $aPagar     = (float) $liq->gross_pay;

        return [
            'sueldo_base'      => round($base, 2),
            'sobreproduccion'  => round($sobre, 2),
            'bonos'            => round($bonos, 2),
            'descuentos'       => round($descuentos, 2),
            // calcular() no guarda aparte el abono al préstamo ni la aportación al fondo: es lo que
            // falta para llegar al monto a pagar.
            'prestamo_y_fondo' => round($base + $sobre + $bonos - $descuentos - $aPagar, 2),
            'a_pagar'          => round($aPagar, 2),
            'cerrada'          => $liq->status === 'closed',
            'unidades'         => (int) $liq->total_units,
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
