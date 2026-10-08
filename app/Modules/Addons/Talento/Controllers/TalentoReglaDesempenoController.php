<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoWeeklyRule;
use App\Modules\Addons\Talento\Models\TalentoWeeklyRuleEvaluation;
use App\Modules\Addons\Talento\Services\PenaltyService;
use App\Modules\Addons\Talento\Support\Actor;
use Illuminate\Http\Request;

/**
 * Reglas de desempeño semanal por combinación de roles (piso/techo de
 * prospectos+ventas por semana -> penalización sugerida / bono). Ver
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
            'min_prospects'             => 'nullable|integer|min:0',
            'min_sales'                 => 'nullable|integer|min:0',
            'penalty_type_id'           => 'nullable|integer|exists:talento_penalty_types,id',
            'bonus_prospects_threshold' => 'nullable|integer|min:0',
            'bonus_sales_threshold'     => 'nullable|integer|min:0',
            'bonus_amount'              => 'nullable|numeric|min:0',
            'active'                    => 'boolean',
        ]);

        $data['roles'] = TalentoWeeklyRule::normalizeRoles($data['roles']);

        // Una sola regla activa por combinación exacta de roles — evita
        // ambigüedad silenciosa en resolveRule() del servicio.
        $dup = TalentoWeeklyRule::active()
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->get()
            ->first(fn($r) => TalentoWeeklyRule::normalizeRoles($r->roles ?? []) === $data['roles']);

        // abort_if() evalúa su mensaje SIEMPRE (PHP evalúa argumentos antes de
        // llamar a la función), aunque la condición sea falsa — con $dup=null
        // (el caso normal, sin duplicado) ". $dup->name" tronaba con "Attempt
        // to read property name on null" incluso cuando SÍ se podía guardar.
        if ($dup) {
            abort(422, 'Ya existe una regla activa para esa combinación exacta de roles: "' . $dup->name . '".');
        }

        return $data;
    }

    // ── Evaluaciones ─────────────────────────────────────────────────────

    public function evaluationsIndex(Request $request)
    {
        $this->authorize('talento.reglas_desempeno.view');

        $q = TalentoWeeklyRuleEvaluation::with(['colaborador.user', 'rule', 'penaltyType'])
            ->orderByDesc('week_start');

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

        $applierColaborador = TalentoColaborador::where('user_id', auth()->id())->first();
        abort_unless($applierColaborador, 422, 'No tienes perfil de colaborador.');

        $notes = trim((string) $request->input('notes', ''));
        $penalty = $this->penaltyService->apply([
            'colaborador_id'  => $eval->colaborador_id,
            'penalty_type_id' => $eval->penalty_type_id,
            'amount'          => $eval->penalty_amount,
            'applied_by'      => $applierColaborador->id,
            'created_by'      => auth()->id(),
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
