<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoCompensationRule;
use App\Modules\Addons\Talento\Models\TalentoCompensationRuleHistory;
use Illuminate\Http\Request;

class TalentoCompensacionController extends Controller
{
    public function index()
    {
        $this->authorize('talento.compensation.view');
        return view('addon-talento::talento.compensacion');
    }

    public function rules(Request $request)
    {
        $this->authorize('talento.compensation.view');

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
            'name'               => 'required|string|max:120',
            'target_type'        => 'required|in:technician,seller,counter,all',
            'base_salary'        => 'required|numeric|min:0',
            'period'             => 'required|in:weekly,biweekly,monthly',
            'weekly_quota_units' => 'required|integer|min:0',
            'monthly_bonus'      => 'nullable|array',
            'conditions'         => 'nullable|array',
            'active'             => 'boolean',
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
            'name'               => 'sometimes|string|max:120',
            'target_type'        => 'sometimes|in:technician,seller,counter,all',
            'base_salary'        => 'sometimes|numeric|min:0',
            'period'             => 'sometimes|in:weekly,biweekly,monthly',
            'weekly_quota_units' => 'sometimes|integer|min:0',
            'monthly_bonus'      => 'nullable|array',
            'conditions'         => 'nullable|array',
            'active'             => 'sometimes|boolean',
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
        $this->authorize('talento.compensation.manage');

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
        $this->authorize('talento.compensation.view');

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
        $this->authorize('talento.compensation.view');

        TalentoColaborador::findOrFail($colaboradorId);

        $latest = TalentoCompensationRuleHistory::where('colaborador_id', $colaboradorId)
            ->orderBy('assigned_at', 'desc')
            ->first();

        return response()->json($latest);
    }
}
