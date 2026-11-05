<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoCompensationRule;
use App\Modules\Addons\Talento\Models\TalentoCompensationRuleHistory;
use App\Models\Task;
use App\Modules\Addons\Talento\Models\TalentoMeta;
use App\Modules\Addons\Talento\Models\TalentoWeeklyRuleEvaluation;
use App\Modules\Addons\Talento\Models\TalentoWorkOrder;
use App\Modules\Addons\Talento\Services\MetaService;
use App\Modules\Addons\Talento\Support\Actor;
use Illuminate\Http\Request;

class TalentoCompensacionController extends Controller
{
    /**
     * Ver la compensación de un colaborador: uno mismo, su supervisor
     * directo, o quien tenga el permiso general de STAFF
     * (talento.compensation.view/.manage) — mismo criterio que
     * `$tieneAccesoAmplio` en TalentoColaboradorController::ficha(), pero
     * sin duplicar esa variable (vive en otro controller).
     */
    private function puedeVerCompensacionDe($colaboradorId): bool
    {
        if (auth()->user()->can('talento.compensation.view') || auth()->user()->can('talento.compensation.manage')) {
            return true;
        }

        $miPropioColaborador = Actor::for(auth()->user())->talento();
        if (! $miPropioColaborador) {
            return false;
        }

        return (string) $miPropioColaborador->id === (string) $colaboradorId
            || $miPropioColaborador->subordinados()->where('id', $colaboradorId)->exists();
    }

    public function index()
    {
        $this->authorize('talento.compensation.view');
        return view('addon-talento::talento.compensacion');
    }

    public function rules(Request $request)
    {
        // Catálogo de reglas — hace falta para el selector de "Asignar
        // regla", así que cualquier supervisor (tiene AL MENOS un
        // subordinado directo) también puede consultarlo, no solo quien
        // tiene el permiso general de STAFF.
        if (! auth()->user()->can('talento.compensation.view')) {
            $esSupervisor = Actor::for(auth()->user())->talento()?->subordinados()->exists();
            abort_unless($esSupervisor, 403);
        }

        $q = TalentoCompensationRule::when($request->active, fn($q) => $q->active())
            ->orderBy('name')
            ->get();

        // Append computed value_per_unit
        $q->each(fn($r) => $r->append('value_per_unit'));

        return response()->json($q);
    }

    public function storeRule(Request $request)
    {
        $this->authorize('talento.compensation.manage');

        $data = $request->validate([
            'name'                         => 'required|string|max:120',
            'target_type'                  => 'required|in:technician,seller,counter,all,accounting,support',
            'base_salary'                  => 'required|numeric|min:0',
            'period'                       => 'required|in:daily,weekly,biweekly,monthly',
            'weekly_quota_units'           => 'required|integer|min:0',
            'monthly_bonus'                => 'nullable|array',
            'conditions'                   => 'nullable|array',
            'active'                       => 'boolean',
            // Item #121 — marco KPI para roles no-técnicos (scaffolding).
            'variable_type'                => 'nullable|string|max:30',
            'kpi_key'                      => 'nullable|string|max:60',
            'formula_config'               => 'nullable|array',
            'valid_from'                   => 'nullable|date',
            'valid_until'                  => 'nullable|date|after_or_equal:valid_from',
            'monthly_cutoff_day'           => 'nullable|integer|min:1|max:31',
            'clawback_days'                => 'nullable|integer|min:0',
            'clawback_requires_collection' => 'nullable|boolean',
        ]);

        $rule = TalentoCompensationRule::create($data);
        $rule->append('value_per_unit');

        return response()->json($rule, 201);
    }

    public function updateRule(Request $request, $id)
    {
        $this->authorize('talento.compensation.manage');

        $rule = TalentoCompensationRule::findOrFail($id);

        $data = $request->validate([
            'name'                         => 'sometimes|string|max:120',
            'target_type'                  => 'sometimes|in:technician,seller,counter,all,accounting,support',
            'base_salary'                  => 'sometimes|numeric|min:0',
            'period'                       => 'sometimes|in:daily,weekly,biweekly,monthly',
            'weekly_quota_units'           => 'sometimes|integer|min:0',
            'monthly_bonus'                => 'nullable|array',
            'conditions'                   => 'nullable|array',
            'active'                       => 'sometimes|boolean',
            'variable_type'                => 'nullable|string|max:30',
            'kpi_key'                      => 'nullable|string|max:60',
            'formula_config'               => 'nullable|array',
            'valid_from'                   => 'nullable|date',
            'valid_until'                  => 'nullable|date|after_or_equal:valid_from',
            'monthly_cutoff_day'           => 'nullable|integer|min:1|max:31',
            'clawback_days'                => 'nullable|integer|min:0',
            'clawback_requires_collection' => 'nullable|boolean',
        ]);

        $rule->update($data);
        $rule->append('value_per_unit');

        return response()->json($rule);
    }

    /**
     * Assign a rule to a collaborator (immutable snapshot history).
     */
    public function assignRule(Request $request, $colaboradorId)
    {
        // David (28-sep): "solo el superior o superiores deberían poder
        // asignarle reglas [de compensación], no uno mismo" — mismo patrón
        // ya usado en TalentoWorkOrderController::store(): el permiso
        // general de gestión (admin/DESARROLLADOR) sigue siendo la vía
        // normal, y se suma como excepción el supervisor DIRECTO
        // (talento_colaboradores.supervisor_id) de este colaborador
        // específico — nunca el colaborador mismo.
        if (! auth()->user()->can('talento.compensation.manage')) {
            $esSuSupervisor = Actor::for(auth()->user())->talento()
                ?->subordinados()
                ->where('id', $colaboradorId)
                ->exists();

            abort_unless($esSuSupervisor, 403, 'No tienes permiso para asignar reglas de compensación a este colaborador.');
        }

        $colaborador = TalentoColaborador::findOrFail($colaboradorId);

        $data = $request->validate([
            'rule_id'     => 'required|exists:talento_compensation_rules,id',
            'assigned_at' => 'nullable|date',
        ]);

        $rule = TalentoCompensationRule::findOrFail($data['rule_id']);

        $entry = TalentoCompensationRuleHistory::create([
            'colaborador_id' => $colaborador->id,
            'rule_id'        => $rule->id,
            'data'           => $rule->toArray(),   // immutable snapshot
            'assigned_at'    => $data['assigned_at'] ?? now(),
        ]);

        return response()->json($entry, 201);
    }

    /**
     * Get full rule history for a collaborator (most recent first).
     */
    public function historyForColaborador($colaboradorId)
    {
        abort_unless($this->puedeVerCompensacionDe($colaboradorId), 403);

        TalentoColaborador::findOrFail($colaboradorId);

        $history = TalentoCompensationRuleHistory::where('colaborador_id', $colaboradorId)
            ->with('rule')
            ->orderBy('assigned_at', 'desc')
            ->get();

        return response()->json($history);
    }

    /**
     * Current effective rule for a collaborator (latest assigned).
     */
    public function currentRule($colaboradorId)
    {
        abort_unless($this->puedeVerCompensacionDe($colaboradorId), 403);

        TalentoColaborador::findOrFail($colaboradorId);

        $latest = TalentoCompensationRuleHistory::where('colaborador_id', $colaboradorId)
            ->orderBy('assigned_at', 'desc')
            ->first();

        return response()->json($latest);
    }

    /** UNI-T M2d: metas vigentes hoy para el colaborador (solo lectura; informativo). Apagado = lista vacía. */
    public function metasVigentes($colaboradorId)
    {
        abort_unless($this->puedeVerCompensacionDe($colaboradorId), 403);

        $col = TalentoColaborador::findOrFail($colaboradorId);
        if (! config('unificacion.metas_m2')) {
            return response()->json(['enabled' => false, 'metas' => []]);
        }

        $roles = $col->user ? $col->user->getRoleNames()->all() : [];
        $svc = app(MetaService::class);
        $metas = [];
        foreach (TalentoMeta::query()->distinct()->pluck('concepto') as $concepto) {
            $m = null;
            foreach ($roles ?: [null] as $rol) {
                $m = $svc->resolver($concepto, array_filter(['persona' => $col->id, 'rol' => $rol]));
                if ($m && $m->alcance_tipo !== 'global') {
                    break;
                }
            }
            if ($m) {
                $metas[] = $m->only(['id', 'concepto', 'alcance_tipo', 'alcance_clave', 'periodo', 'valor', 'unidad', 'afecta_pago', 'version', 'vigente_desde', 'vigente_hasta'])
                    + $this->resultadoYEvidencia($col, $m);
            }
        }

        return response()->json(['enabled' => true, 'metas' => $metas]);
    }

    /**
     * UNI-T M3: resultado de la última evaluación semanal que usó esta meta y los registros que lo respaldan
     * (órdenes validadas de la misma ventana). Solo lectura; sin evaluación → resultado null.
     */
    private function resultadoYEvidencia(TalentoColaborador $col, TalentoMeta $meta): array
    {
        $ev = TalentoWeeklyRuleEvaluation::where('colaborador_id', $col->id)
            ->where('meta_id', $meta->id)
            ->orderByDesc('week_end')
            ->first();
        if (! $ev) {
            return ['resultado' => null, 'evidencia' => []];
        }

        $ini = $ev->week_start->copy()->startOfDay();
        $fin = $ev->week_end->copy()->endOfDay();
        $ordenes = TalentoWorkOrder::where('colaborador_id', $col->id)->validatedBillable()
            ->whereBetween('validated_at', [$ini, $fin])->get(['id', 'points', 'validated_at'])
            ->map(fn ($o) => ['tipo' => 'orden', 'id' => $o->id, 'puntos' => $o->points, 'validada' => (string) $o->validated_at, 'url' => '/talento/api/ordenes/'.$o->id])->all();
        $tareas = $col->user_id
            ? Task::whereNotNull('talento_type_id')->where('is_billable', true)->where('points', '>', 0)
                ->where('status', 'Done')->whereBetween('validated_at', [$ini, $fin])
                ->whereHas('users', fn ($q) => $q->where('users.id', $col->user_id))->get(['id', 'points', 'validated_at'])
                ->map(fn ($t) => ['tipo' => 'tarea', 'id' => $t->id, 'puntos' => $t->points, 'validada' => (string) $t->validated_at, 'url' => '/tasks/'.$t->id])->all()
            : [];

        return [
            'resultado' => [
                'semana_inicio' => $ev->week_start->toDateString(),
                'semana_fin'    => $ev->week_end->toDateString(),
                'prospectos'    => $ev->prospects_count,
                'ventas'        => $ev->sales_count,
                'resultado'     => $ev->outcome,
                'meta_version'  => $ev->meta_version,
            ],
            'evidencia' => array_merge($ordenes, $tareas),
        ];
    }
}
