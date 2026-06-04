<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Talento\Models\TalentoActivityType;
use App\Modules\Addons\Talento\Models\TalentoLevel;
use App\Modules\Addons\Talento\Models\TalentoWorkOrderType;
use App\Modules\Addons\Talento\Services\LevelService;
use Illuminate\Http\Request;

class TalentoLevelController extends Controller
{
    public function __construct(private LevelService $svc) {}

    // ── Web view ───────────────────────────────────────────────────────────

    public function index()
    {
        return view('addon-talento::talento.niveles');
    }

    // ── Level CRUD ─────────────────────────────────────────────────────────

    public function levelsIndex()
    {
        return response()->json(
            TalentoLevel::orderBy('rank')->get()
        );
    }

    public function storeLevel(Request $request)
    {
        $data = $request->validate([
            'name'                    => 'required|string|max:80',
            'rank'                    => 'required|integer|min:1',
            'required_certifications' => 'required|array',
            'base_salary'             => 'required|numeric|min:0',
            'active'                  => 'boolean',
        ]);
        return response()->json(TalentoLevel::create($data), 201);
    }

    public function updateLevel(Request $request, int $id)
    {
        $level = TalentoLevel::findOrFail($id);
        $data  = $request->validate([
            'name'                    => 'sometimes|string|max:80',
            'rank'                    => 'sometimes|integer|min:1',
            'required_certifications' => 'sometimes|array',
            'base_salary'             => 'sometimes|numeric|min:0',
            'active'                  => 'boolean',
        ]);
        $level->update($data);
        return response()->json($level);
    }

    // ── Eligibility & Promotion ────────────────────────────────────────────

    public function eligibility(int $colaboradorId)
    {
        return response()->json($this->svc->eligibility($colaboradorId));
    }

    public function promote(Request $request, int $colaboradorId)
    {
        $data = $request->validate([
            'level_id' => 'required|integer|exists:talento_levels,id',
            'reason'   => 'in:promotion,manual',
        ]);

        $assignment = $this->svc->promote(
            $colaboradorId,
            $data['level_id'],
            $data['reason'] ?? 'promotion',
            auth()->id()
        );

        return response()->json($assignment->load('level'));
    }

    public function levelHistory(int $colaboradorId)
    {
        $assignments = \App\Modules\Addons\Talento\Models\TalentoLevelAssignment::with('level')
            ->where('colaborador_id', $colaboradorId)
            ->orderByDesc('assigned_at')
            ->get();
        return response()->json($assignments);
    }

    // ── Gating config ──────────────────────────────────────────────────────

    public function workOrderTypes()
    {
        return response()->json(
            TalentoWorkOrderType::with('requiredLevel')->orderBy('name')->get()
        );
    }

    public function setWorkOrderTypeLevel(Request $request, int $typeId)
    {
        $type = TalentoWorkOrderType::findOrFail($typeId);
        $data = $request->validate(['required_level_id' => 'nullable|integer|exists:talento_levels,id']);
        $type->update($data);
        return response()->json($type->load('requiredLevel'));
    }

    public function activityTypes()
    {
        return response()->json(
            TalentoActivityType::with('requiredLevel')->orderBy('name')->get()
        );
    }

    public function setActivityTypeLevel(Request $request, int $typeId)
    {
        $type = TalentoActivityType::findOrFail($typeId);
        $data = $request->validate(['required_level_id' => 'nullable|integer|exists:talento_levels,id']);
        $type->update($data);
        return response()->json($type->load('requiredLevel'));
    }
}
