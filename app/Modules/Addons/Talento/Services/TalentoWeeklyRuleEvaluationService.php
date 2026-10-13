<?php

namespace App\Modules\Addons\Talento\Services;

use App\Models\Payment;
use App\Models\User;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoWeeklyRule;
use App\Modules\Addons\Talento\Models\TalentoWeeklyRuleEvaluation;
use App\Modules\Addons\Talento\Models\TalentoWorkOrder;
use App\Services\CalculateBalanceSellerService;

/**
 * Evalúa el desempeño semanal (prospectos/ventas) de un colaborador contra
 * la regla que le corresponde por su combinación EXACTA de roles, y deja
 * el resultado como una fila en talento_weekly_rule_evaluations.
 *
 * Nunca aplica una penalización real por sí solo — solo la deja
 * 'penalty_pending' con lo que la regla sugiere; aplicarla de verdad
 * (descuento real, inmutable) sigue exigiendo que un supervisor la
 * confirme (ver TalentoReglaDesempenoController::confirmarPenalizacion(),
 * que reusa PenaltyService::apply() tal cual).
 *
 * Idempotente: UNIQUE(colaborador_id, week_start) en la tabla — una
 * segunda llamada para la misma semana no duplica nada.
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

    public function evaluateColaborador(TalentoColaborador $colaborador, array $week): ?TalentoWeeklyRuleEvaluation
    {
        $existing = TalentoWeeklyRuleEvaluation::where('colaborador_id', $colaborador->id)
            ->where('week_start', $week['period_start'])
            ->first();
        if ($existing) {
            return $existing;
        }

        /** @var User|null $user */
        $user = $colaborador->user;
        if (!$user) {
            return null;
        }

        $roleNames = $user->getRoleNames()->diff(self::SYSTEM_ROLES)->values()->all();
        $rule = $this->resolveRule($roleNames);

        [$prospects, $sales] = $this->medirUnidades($rule?->metric_type ?? 'ventas', $user, $colaborador, $week);

        $base = [
            'colaborador_id'   => $colaborador->id,
            'rule_id'          => $rule?->id,
            'week_start'       => $week['period_start'],
            'week_end'         => $week['period_end'],
            'prospects_count'  => $prospects,
            'sales_count'      => $sales,
            'evaluated_at'     => now(),
        ];

        if (!$rule) {
            return TalentoWeeklyRuleEvaluation::create($base + ['outcome' => 'no_rule']);
        }

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
     * Busca la regla activa cuyo conjunto de roles coincide EXACTO con
     * $roleNames. Si no hay match exacto, toma entre las reglas cuyo
     * conjunto de roles sea subconjunto de $roleNames la de MÁS roles (la
     * más específica); empate -> la más reciente (id mayor).
     */
    private function resolveRule(array $roleNames): ?TalentoWeeklyRule
    {
        $userRoles = TalentoWeeklyRule::normalizeRoles($roleNames);

        $candidate = null;
        $candidateSize = -1;

        foreach (TalentoWeeklyRule::active()->get() as $rule) {
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
