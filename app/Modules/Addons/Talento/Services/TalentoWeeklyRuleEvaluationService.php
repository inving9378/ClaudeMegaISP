<?php

namespace App\Modules\Addons\Talento\Services;

use App\Models\Payment;
use App\Models\User;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoHealthBonusLog;
use App\Modules\Addons\Talento\Models\TalentoWeeklyRule;
use App\Modules\Addons\Talento\Models\TalentoWeeklyRuleEvaluation;
use App\Modules\Addons\Talento\Models\TalentoWorkOrder;
use App\Services\CalculateBalanceSellerService;
use Illuminate\Support\Collection;

/**
 * Evalúa el desempeño semanal de un colaborador contra la(s) regla(s) que le
 * corresponda(n) por su combinación EXACTA de roles, y deja el resultado como
 * fila(s) en talento_weekly_rule_evaluations.
 *
 * Desde el 2026-10-13 (pedido directo de Irving: cantidad y calidad de señal
 * son dos ejes de negocio INDEPENDIENTES — un técnico puede cumplir cantidad
 * y fallar calidad, o viceversa) un colaborador puede tener MÁS DE UNA
 * evaluación la misma semana, una por cada metric_type con una regla activa
 * que le aplique (ver resolveRule($roleNames, $metricType)). Por eso
 * evaluateColaborador() devuelve una Collection, no una sola fila.
 *
 * Nunca aplica una penalización real por sí solo — solo la deja
 * 'penalty_pending' con lo que la regla sugiere; aplicarla de verdad
 * (descuento real, inmutable) sigue exigiendo que un supervisor la
 * confirme (ver TalentoReglaDesempenoController::confirmarPenalizacion(),
 * que reusa PenaltyService::apply() tal cual).
 *
 * Idempotente: UNIQUE(colaborador_id, week_start, rule_id) en la tabla — una
 * segunda llamada para la misma semana no duplica nada (por regla).
 */
class TalentoWeeklyRuleEvaluationService
{
    // 'Super Administrador' es el duplicado ES del mismo rol 'super-administrator'
    // (ver CLAUDE.md — "Duplicado super-admin ES/EN", pendiente de consolidar en
    // Fase 4) -- se excluye aquí también para no ofrecerlo como combinación de
    // negocio seleccionable, aunque TalentoColaboradorSyncService (otro alcance,
    // sin UI de selección) no lo tenga en su propia constante homónima.
    private const SYSTEM_ROLES = ['super-administrator', 'Super Administrador', 'ADMINISTRADOR_COMPLETO', 'DESARROLLADOR'];

    public function __construct(private CalculateBalanceSellerService $balanceService) {}

    /** @return Collection<int, TalentoWeeklyRuleEvaluation> */
    public function evaluateColaborador(TalentoColaborador $colaborador, array $week): Collection
    {
        /** @var User|null $user */
        $user = $colaborador->user;
        if (!$user) {
            return collect();
        }

        $roleNames = $user->getRoleNames()->diff(self::SYSTEM_ROLES)->values()->all();

        $metricTypes = TalentoWeeklyRule::active()->distinct()->pluck('metric_type');

        // Reglas que SÍ aplican a este colaborador, una por metric_type en
        // juego. Los metric_types que no le aplican (ej. 'ventas' para un
        // técnico puro) se descartan aquí mismo, ANTES de tocar la BD — no
        // tiene sentido dejar una fila 'no_rule' por cada eje ajeno cada
        // semana para siempre, eso infla la tabla sin ningún dato útil.
        $reglasQueAplican = $metricTypes
            ->map(fn (string $metricType) => $this->resolveRule($roleNames, $metricType))
            ->filter()
            ->values();

        if ($reglasQueAplican->isEmpty()) {
            // Ni una sola regla de ningún eje le aplica (o no hay ninguna
            // regla activa en todo el sistema): se conserva el 'no_rule'
            // original, una sola fila, idempotente por código.
            $eval = $this->evaluateUnaRegla($colaborador, $user, $week, null);
            return $eval ? collect([$eval]) : collect();
        }

        return $reglasQueAplican
            ->map(fn (TalentoWeeklyRule $rule) => $this->evaluateUnaRegla($colaborador, $user, $week, $rule))
            ->filter()
            ->values();
    }

    private function evaluateUnaRegla(TalentoColaborador $colaborador, User $user, array $week, ?TalentoWeeklyRule $rule): ?TalentoWeeklyRuleEvaluation
    {
        $existing = TalentoWeeklyRuleEvaluation::where('colaborador_id', $colaborador->id)
            ->where('week_start', $week['period_start'])
            ->where('rule_id', $rule?->id)
            ->first();
        if ($existing) {
            return $existing;
        }

        if (!$rule) {
            return TalentoWeeklyRuleEvaluation::create([
                'colaborador_id'  => $colaborador->id,
                'rule_id'         => null,
                'week_start'      => $week['period_start'],
                'week_end'        => $week['period_end'],
                'prospects_count' => 0,
                'sales_count'     => 0,
                'evaluated_at'    => now(),
                'outcome'         => 'no_rule',
            ]);
        }

        if ($rule->metric_type === 'calidad_senal') {
            return $this->evaluarCalidadSenal($colaborador, $week, $rule);
        }

        [$prospects, $sales] = $this->medirUnidades($rule->metric_type, $user, $colaborador, $week);

        $base = [
            'colaborador_id'   => $colaborador->id,
            'rule_id'          => $rule->id,
            'week_start'       => $week['period_start'],
            'week_end'         => $week['period_end'],
            'prospects_count'  => $prospects,
            'sales_count'      => $sales,
            'evaluated_at'     => now(),
        ];

        $belowFloor = ($rule->min_prospects !== null && $prospects < $rule->min_prospects)
            || ($rule->min_sales !== null && $sales < $rule->min_sales);

        if ($belowFloor) {
            $type = $rule->penaltyType;
            return TalentoWeeklyRuleEvaluation::create($base + [
                'outcome'          => 'penalty_pending',
                'penalty_type_id'  => $rule->penalty_type_id,
                'penalty_amount'   => $type?->amount,
            ]);
        }

        $meetsCeiling = $rule->bonus_prospects_threshold !== null || $rule->bonus_sales_threshold !== null;
        $overCeiling = $meetsCeiling
            && ($rule->bonus_prospects_threshold === null || $prospects >= $rule->bonus_prospects_threshold)
            && ($rule->bonus_sales_threshold === null || $sales >= $rule->bonus_sales_threshold);

        if ($overCeiling) {
            return TalentoWeeklyRuleEvaluation::create($base + [
                'outcome'               => 'bonus',
                'bonus_amount_applied'  => $rule->bonus_amount,
            ]);
        }

        return TalentoWeeklyRuleEvaluation::create($base + ['outcome' => 'meets_quota']);
    }

    /**
     * Calidad de señal (dBm) — eje INVERTIDO respecto a cantidad: aquí
     * "unidades" = número de instalaciones de la semana con pérdida de señal
     * FUERA del umbral de ESTA regla (violaciones), reusando el `loss_db` ya
     * calculado por HealthBonusService (TalentoHealthBonusLog, per-OT,
     * existe desde antes de esta fase) — nunca se recalcula la pérdida de
     * señal en paralelo. Más violaciones = peor (al revés que cantidad, donde
     * más es mejor), así que el piso/techo también se leen al revés:
     *   - min_sales (reusado) = violaciones mínimas que YA penalizan.
     *   - bonus_sales_threshold (reusado) = violaciones máximas toleradas
     *     para ganar el bono (típicamente 0 = "cero instalaciones fuera de
     *     rango esta semana").
     * Sin NINGUNA instalación medible esta semana (loss_db registrado, sea
     * cual sea su valor) -> 'sin_datos', nunca bono/penalización: no hay
     * evidencia de calidad buena NI mala, y "0 instalaciones = 0
     * violaciones" premiaría a quien no trabajó nada esa semana.
     */
    private function evaluarCalidadSenal(TalentoColaborador $colaborador, array $week, TalentoWeeklyRule $rule): TalentoWeeklyRuleEvaluation
    {
        $logs = TalentoHealthBonusLog::where('colaborador_id', $colaborador->id)
            ->whereNotNull('loss_db')
            ->whereDate('checked_at', '>=', $week['period_start'])
            ->whereDate('checked_at', '<=', $week['period_end'])
            ->get(['loss_db']);

        $base = [
            'colaborador_id'   => $colaborador->id,
            'rule_id'          => $rule->id,
            'week_start'       => $week['period_start'],
            'week_end'         => $week['period_end'],
            'prospects_count'  => 0,
            'evaluated_at'     => now(),
        ];

        if ($logs->isEmpty()) {
            return TalentoWeeklyRuleEvaluation::create($base + ['sales_count' => 0, 'outcome' => 'sin_datos']);
        }

        $maxLossDb = $rule->max_loss_db !== null ? (float) $rule->max_loss_db : 1.0;
        $violaciones = $logs->filter(fn ($log) => (float) $log->loss_db > $maxLossDb)->count();

        $base['sales_count'] = $violaciones;

        $penalizaDesde = $rule->min_sales; // violaciones mínimas que ya penalizan
        if ($penalizaDesde !== null && $violaciones >= $penalizaDesde) {
            $type = $rule->penaltyType;
            return TalentoWeeklyRuleEvaluation::create($base + [
                'outcome'          => 'penalty_pending',
                'penalty_type_id'  => $rule->penalty_type_id,
                'penalty_amount'   => $type?->amount,
            ]);
        }

        $bonoHasta = $rule->bonus_sales_threshold; // violaciones máximas toleradas para el bono
        if ($bonoHasta !== null && $violaciones <= $bonoHasta) {
            return TalentoWeeklyRuleEvaluation::create($base + [
                'outcome'              => 'bonus',
                'bonus_amount_applied' => $rule->bonus_amount,
            ]);
        }

        return TalentoWeeklyRuleEvaluation::create($base + ['outcome' => 'meets_quota']);
    }

    /**
     * "Unidades" de la semana según el metric_type de la regla que le
     * corresponda al colaborador. Devuelve siempre [prospects, sales] para
     * no tocar el resto de evaluateColaborador() (floor/ceiling/bono ya
     * comparan solo esos dos números) — 'tecnico'/'mostrador' solo llenan
     * el segundo (reusando la columna sales_count), dejando prospects en 0
     * porque no aplica.
     *
     * 'tecnico' reusa EXACTO el mismo criterio que ya le muestra el propio
     * colaborador en "Mi progreso" (DashboardService::calidadTecnico():
     * status completed/validated, por created_at) — la regla de la fase 2
     * nunca puede medir un número distinto al que la fase 1 ya le enseña,
     * o el colaborador vería dos cifras divergentes del mismo trabajo.
     * 'mostrador' reusa igual el criterio de DashboardService::
     * progresoMostrador() (pagos con add_by = este usuario, por created_at).
     *
     * ⚠️ $week['period_start']/['period_end'] son strings de FECHA sola
     * ('Y-m-d', para las columnas week_start/week_end), NO los instantes
     * precisos del corte de PayWeek (start_instant/end_instant, Sáb 18:00).
     * whereBetween() con esas fechas desnudas interpretaría el límite
     * superior como medianoche, recortando todo lo hecho entre 00:00 y
     * 18:00 del sábado de cierre. Se usa whereDate() (día calendario
     * completo) en su lugar — EXACTO el mismo criterio que ya usa
     * CalculateBalanceSellerService::getProspects()/getSales() para
     * 'ventas' (ver más abajo): ninguna métrica de esta función respeta
     * todavía el corte fino de las 18:00, consistente entre las tres.
     */
    private function medirUnidades(string $metricType, User $user, TalentoColaborador $colaborador, array $week): array
    {
        if ($metricType === 'tecnico') {
            $completados = (int) TalentoWorkOrder::where('colaborador_id', $colaborador->id)
                ->whereIn('status', ['completed', 'validated'])
                ->whereDate('created_at', '>=', $week['period_start'])
                ->whereDate('created_at', '<=', $week['period_end'])
                ->count();
            return [0, $completados];
        }

        if ($metricType === 'mostrador') {
            $pagos = (int) Payment::where('add_by', $user->id)
                ->whereDate('created_at', '>=', $week['period_start'])
                ->whereDate('created_at', '<=', $week['period_end'])
                ->count();
            return [0, $pagos];
        }

        $prospects = $this->balanceService->getProspects($user, $week['period_start'], $week['period_end']);
        $sales = $this->balanceService->getSales($user, $week['period_start'], $week['period_end'])->count();
        return [$prospects, $sales];
    }

    /**
     * Busca la regla activa de ESTE metric_type cuyo conjunto de roles
     * coincide EXACTO con $roleNames. Si no hay match exacto, toma entre las
     * reglas cuyo conjunto de roles sea subconjunto de $roleNames la de MÁS
     * roles (la más específica); empate -> la más reciente (id mayor). El
     * filtro por metric_type es lo que permite que un técnico tenga a la vez
     * una regla de cantidad Y una de calidad activas sin pisarse.
     */
    private function resolveRule(array $roleNames, string $metricType): ?TalentoWeeklyRule
    {
        $userRoles = TalentoWeeklyRule::normalizeRoles($roleNames);

        $candidate = null;
        $candidateSize = -1;

        foreach (TalentoWeeklyRule::active()->where('metric_type', $metricType)->get() as $rule) {
            $ruleRoles = TalentoWeeklyRule::normalizeRoles($rule->roles ?? []);
            if (empty($ruleRoles)) {
                continue;
            }

            if ($ruleRoles === $userRoles) {
                return $rule; // match exacto, gana siempre
            }

            $isSubset = empty(array_diff($ruleRoles, $userRoles));
            if (!$isSubset) {
                continue;
            }

            $size = count($ruleRoles);
            if ($size > $candidateSize || ($size === $candidateSize && $rule->id > $candidate->id)) {
                $candidate = $rule;
                $candidateSize = $size;
            }
        }

        return $candidate;
    }
}
