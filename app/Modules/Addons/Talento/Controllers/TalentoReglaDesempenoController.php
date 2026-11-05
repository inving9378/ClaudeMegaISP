<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Talento\Models\TalentoWeeklyRule;
use App\Modules\Addons\Talento\Models\TalentoWeeklyRuleEvaluation;
use App\Modules\Addons\Talento\Services\PenaltyService;
use App\Modules\Addons\Talento\Support\Actor;
use Illuminate\Http\Request;

/**
 * Reglas de desempeño semanal por combinación de roles (piso/techo de
 * unidades por semana -> penalización sugerida / bono). `metric_type`
 * decide qué cuenta como "unidades" (ventas/prospectos, completados de
 * técnico, o pagos capturados de mostrador) — ver
 * TalentoWeeklyRuleEvaluationService::medirUnidades(). Ver
 * TalentoWeeklyRuleEvaluationService para el motor de evaluación real
 * (corrido por el comando talento:evaluar-reglas-semanales, domingos).
 */
class TalentoReglaDesempenoController extends Controller
{
    public function __construct(private PenaltyService $penaltyService) {}

    public function index()
    {
        return view('addon-talento::talento.reglas_desempeno');
    }

    // ── Reglas ───────────────────────────────────────────────────────────

    public function rulesIndex()
    {
        $this->authorize('talento.reglas_desempeno.view');
        return response()->json(
            TalentoWeeklyRule::with('penaltyType')->orderBy('name')->get()
        );
    }

    public function storeRule(Request $request)
    {
        $this->authorize('talento.reglas_desempeno.manage');
        $data = $this->validateRule($request);
        $rule = TalentoWeeklyRule::create($data + ['created_by' => auth()->id()]);
        return response()->json($rule->load('penaltyType'), 201);
    }

    public function updateRule(Request $request, int $id)
    {
        $this->authorize('talento.reglas_desempeno.manage');
        $rule = TalentoWeeklyRule::findOrFail($id);
        $data = $this->validateRule($request, $rule->id);
        $rule->update($data + ['updated_by' => auth()->id()]);
        return response()->json($rule->fresh('penaltyType'));
    }

    private function validateRule(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'name'                      => 'required|string|max:150',
            'roles'                     => 'required|array|min:1',
            'roles.*'                   => 'string',
            'metric_type'               => 'required|in:ventas,tecnico,mostrador,calidad_senal',
            'max_loss_db'               => 'nullable|numeric|min:0|max:50',
            'min_prospects'             => 'nullable|integer|min:0',
            'min_sales'                 => 'nullable|integer|min:0',
            'penalty_type_id'           => 'nullable|integer|exists:talento_penalty_types,id',
            'bonus_prospects_threshold' => 'nullable|integer|min:0',
            'bonus_sales_threshold'     => 'nullable|integer|min:0',
            'bonus_amount'              => 'nullable|numeric|min:0',
            'active'                    => 'boolean',
        ]);

        $data['roles'] = TalentoWeeklyRule::normalizeRoles($data['roles']);

        // Una sola regla activa por combinación exacta de roles POR
        // metric_type — evita ambigüedad silenciosa en resolveRule() del
        // servicio (que ahora también filtra por metric_type). Desde que
        // existe calidad_senal como eje independiente, un mismo rol SÍ puede
        // tener una regla de cantidad Y otra de calidad a la vez — el
        // duplicado solo aplica dentro del mismo metric_type.
        $dup = TalentoWeeklyRule::active()
            ->where('metric_type', $data['metric_type'])
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->get()
            ->first(fn($r) => TalentoWeeklyRule::normalizeRoles($r->roles ?? []) === $data['roles']);

        // abort_if() evalúa su mensaje SIEMPRE (PHP evalúa argumentos antes de
        // llamar a la función), aunque la condición sea falsa — con $dup=null
        // (el caso normal, sin duplicado) ". $dup->name" tronaba con "Attempt
        // to read property name on null" incluso cuando SÍ se podía guardar.
        if ($dup) {
            abort(422, 'Ya existe una regla activa de "' . $data['metric_type'] . '" para esa combinación exacta de roles: "' . $dup->name . '".');
        }

        return $data;
    }

    // ── Evaluaciones ─────────────────────────────────────────────────────

    /**
     * Antes exigía talento.reglas_desempeno.view (admin-only) sin excepción —
     * un supervisor real nunca tenía forma de ver las evaluaciones de SU
     * equipo, pese a que confirmarPenalizacion() sí le permite confirmarlas
     * (bug real encontrado 2026-10-13: un supervisor podía en teoría
     * confirmar, pero no tenía cómo enterarse de qué confirmar). Mismo
     * criterio "staff ve todo, el resto solo uno mismo + su equipo directo"
     * que ya usa TalentoWorkOrderController::data().
     */
    public function evaluationsIndex(Request $request)
    {
        $esStaff = auth()->user()->can('talento.reglas_desempeno.view')
            || auth()->user()->can('talento.penalties.manage');

        $q = TalentoWeeklyRuleEvaluation::with(['colaborador.user', 'rule', 'penaltyType'])
            ->orderByDesc('week_start');

        if (! $esStaff) {
            $miPropioColaborador = Actor::for(auth()->user())->talento();
            abort_if(! $miPropioColaborador, 403);

            $idsVisibles = $miPropioColaborador->subordinados()->pluck('id')
                ->push($miPropioColaborador->id)
                ->unique()
                ->values()
                ->all();
            $q->whereIn('colaborador_id', $idsVisibles);
        }

        if ($request->filled('outcome')) {
            $q->where('outcome', $request->outcome);
        }

        return response()->json($q->paginate(50));
    }

    /**
     * Confirma de verdad una penalización pendiente — reusa íntegro
     * PenaltyService::apply() (mismo cargo contable inmutable, mismo
     * guard de "solo un supervisor humano, nunca uno mismo") que ya usa
     * TalentoPenaltyController::applyPenalty(). Nunca se construye un
     * camino de dinero paralelo aquí.
     */
    public function confirmarPenalizacion(Request $request, int $evaluationId)
    {
        $eval = TalentoWeeklyRuleEvaluation::findOrFail($evaluationId);
        abort_unless($eval->outcome === 'penalty_pending', 422, 'Esta evaluación no tiene una penalización pendiente.');

        if (! auth()->user()->can('talento.penalties.manage')) {
            $esSuSupervisor = Actor::for(auth()->user())->talento()
                ?->subordinados()
                ->where('id', $eval->colaborador_id)
                ->exists();
            abort_unless($esSuSupervisor, 403, 'No tienes permiso para confirmar esta penalización.');
        }

        // Un admin/DESARROLLADOR real a menudo NO tiene su propia ficha de
        // colaborador (no es insólito — es exactamente el caso real
        // encontrado 2026-10-13: ni "Mi equipo" ni esto funcionaban para una
        // cuenta de sistema sin ficha propia). Atribuir a MEGAISP (mismo bot
        // de sistema que ya usa PaymentApplicationService::resolveSystemUserId()
        // para pagos automáticos) en vez de bloquear la acción — crea su
        // ficha "ligera" la primera vez si todavía no existe, idempotente.
        $applierColaborador = $this->penaltyService->firmanteDe(auth()->user());
        abort_unless($applierColaborador, 422, 'No tienes perfil de colaborador.');

        $notes = trim((string) $request->input('notes', ''));
        $penalty = $this->penaltyService->apply([
            'colaborador_id'  => $eval->colaborador_id,
            'penalty_type_id' => $eval->penalty_type_id,
            'amount'          => $eval->penalty_amount,
            'applied_by'      => $applierColaborador->id,
            'created_by'      => auth()->id(),
            // La cuota se incumplió en LA SEMANA EVALUADA, no necesariamente en la
            // que un supervisor se tarde en confirmarla — el débito debe caer en
            // esa liquidación, no en la de "hoy" (ver PenaltyService::apply()).
            'period_start'    => $eval->week_start->toDateString(),
            'period_end'      => $eval->week_end->toDateString(),
            'notes'           => $notes !== ''
                ? $notes
                : "Confirmado desde regla de desempeño semanal (semana {$eval->week_start->toDateString()}).",
        ]);

        $eval->update([
            'outcome'      => 'penalty_applied',
            'penalty_id'   => $penalty->id,
            'confirmed_by' => $applierColaborador->id,
            'confirmed_at' => now(),
        ]);

        return response()->json($eval->fresh(['penalty', 'confirmedBy']));
    }
}
