<?php

namespace App\Modules\Addons\Talento\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Talento\Models\TalentoActivityType;
use App\Modules\Addons\Talento\Models\TalentoProject;
use App\Modules\Addons\Talento\Models\TalentoProjectActivity;
use App\Modules\Addons\Talento\Models\TalentoProjectActivityReport;
use App\Modules\Addons\Talento\Services\ProjectActivityService;
use App\Modules\Addons\Talento\Services\ProjectBonusService;
use Illuminate\Http\Request;

class TalentoProjectController extends Controller
{
    public function __construct(
        private ProjectActivityService $activityService,
        private ProjectBonusService    $bonusService
    ) {}

    // ── Vista ────────────────────────────────────────────────────────────────

    public function index()
    {
        $this->authorize('talento.projects.view');
        return view('addon-talento::talento.proyectos');
    }

    // ── Proyectos CRUD ────────────────────────────────────────────────────────

    public function data(Request $request)
    {
        $this->authorize('talento.projects.view');

        $q = TalentoProject::with('lead.user')
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%$s%"))
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json($q);
    }

    public function store(Request $request)
    {
        $this->authorize('talento.projects.manage');

        $data = $request->validate([
            'name'                => 'required|string|max:160',
            'description'         => 'nullable|string',
            'status'              => 'required|in:planning,active,paused,done',
            'lead_colaborador_id' => 'nullable|exists:talento_colaboradores,id',
            'start_date'          => 'nullable|date',
            'end_date'            => 'nullable|date',
            'bonus_amount'        => 'nullable|numeric|min:0',
            'bonus_scale'         => 'nullable|array',
            'bonus_scale.*.threshold_pct' => 'required_with:bonus_scale|numeric|between:0,100',
            'bonus_scale.*.amount'        => 'required_with:bonus_scale|numeric|min:0',
        ]);

        $project = TalentoProject::create($data);
        return response()->json($project->load('lead.user'), 201);
    }

    public function show($id)
    {
        $this->authorize('talento.projects.view');

        $project = TalentoProject::with([
            'lead.user',
            'activities.activityType',
            'activities.reports.participants.colaborador.user',
        ])->findOrFail($id);

        // Compute progress per activity
        $project->activities->each(function ($act) {
            $act->approved_total  = $act->approvedTotal();
            $act->remaining       = $act->remaining();
            $act->progress_pct    = $act->progressPct();
        });

        // Overall project progress
        $totalPlanned  = $project->activities->sum('planned_quantity');
        $totalApproved = $project->activities->sum('approved_total');
        $project->overall_pct = $totalPlanned > 0
            ? round(min(100, $totalApproved / $totalPlanned * 100), 1) : 0;

        // Per-collaborator production summary
        $colPoints = [];
        foreach ($project->activities as $act) {
            foreach ($act->reports as $r) {
                if (!in_array($r->status, ['approved', 'capped'])) continue;
                foreach ($r->participants as $p) {
                    $colId = $p->colaborador_id;
                    if (!isset($colPoints[$colId])) {
                        $colPoints[$colId] = [
                            'colaborador_id' => $colId,
                            'name' => $p->colaborador?->user?->name ?? '—',
                            'points' => 0.0,
                            'quantity' => 0.0,
                        ];
                    }
                    $colPoints[$colId]['points']   += (float)$p->points_earned;
                    $colPoints[$colId]['quantity']  += (float)$p->quantity_share;
                }
            }
        }
        $project->collaborator_summary = array_values($colPoints);

        return response()->json($project);
    }

    public function update(Request $request, $id)
    {
        $this->authorize('talento.projects.manage');

        $project = TalentoProject::findOrFail($id);

        $data = $request->validate([
            'name'                => 'sometimes|string|max:160',
            'description'         => 'nullable|string',
            'status'              => 'sometimes|in:planning,active,paused,done',
            'lead_colaborador_id' => 'nullable|exists:talento_colaboradores,id',
            'start_date'          => 'nullable|date',
            'end_date'            => 'nullable|date',
            'bonus_amount'        => 'nullable|numeric|min:0',
            'bonus_scale'         => 'nullable|array',
        ]);

        $project->update($data);
        return response()->json($project->load('lead.user'));
    }

    // ── Pool de actividades ───────────────────────────────────────────────────

    public function addActivity(Request $request, $projectId)
    {
        $this->authorize('talento.projects.manage');

        TalentoProject::findOrFail($projectId);

        $data = $request->validate([
            'activity_type_id' => 'required|exists:talento_activity_types,id',
            'planned_quantity'  => 'required|numeric|min:0.0001',
            'location_notes'    => 'nullable|string',
        ]);

        $act = TalentoProjectActivity::create(array_merge($data, [
            'project_id' => $projectId,
            'created_by' => auth()->id(),
        ]));

        return response()->json($act->load('activityType'), 201);
    }

    public function updateActivity(Request $request, $projectId, $actId)
    {
        $this->authorize('talento.projects.manage');

        $act = TalentoProjectActivity::where('project_id', $projectId)->findOrFail($actId);

        $data = $request->validate([
            'planned_quantity' => 'sometimes|numeric|min:0.0001',
            'location_notes'   => 'nullable|string',
        ]);

        // Cannot reduce planned_quantity below already approved
        if (isset($data['planned_quantity'])) {
            $approved = $act->approvedTotal();
            if ((float)$data['planned_quantity'] < $approved) {
                return response()->json([
                    'error' => "No se puede reducir el techo a {$data['planned_quantity']} — ya hay {$approved} aprobados."
                ], 422);
            }
        }

        $act->update($data);
        return response()->json($act->load('activityType'));
    }

    // ── Reportes de actividad ─────────────────────────────────────────────────

    public function submitReport(Request $request, $projectId, $actId)
    {
        $this->authorize('talento.project_reports.create');

        TalentoProjectActivity::where('project_id', $projectId)->findOrFail($actId);

        $data = $request->validate([
            'quantity'        => 'required|numeric|min:0.0001',
            'report_date'     => 'required|date',
            'colaborador_ids' => 'required|array|min:1',
            'colaborador_ids.*'=> 'exists:talento_colaboradores,id',
            'notes'           => 'nullable|string',
        ]);

        try {
            $report = $this->activityService->submitReport(
                $actId,
                (float)$data['quantity'],
                $data['report_date'],
                $data['colaborador_ids'],
                $data['notes'] ?? null
            );
            return response()->json($report, 201);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function approveReport(Request $request, $reportId)
    {
        $this->authorize('talento.project_reports.approve');

        try {
            $report = $this->activityService->approve((int)$reportId);
            return response()->json($report);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function listReports(Request $request, $projectId, $actId)
    {
        $this->authorize('talento.project_reports.view');

        TalentoProjectActivity::where('project_id', $projectId)->findOrFail($actId);

        $reports = TalentoProjectActivityReport::with('participants.colaborador.user', 'reportedBy')
            ->where('project_activity_id', $actId)
            ->orderBy('report_date', 'desc')
            ->get();

        return response()->json($reports);
    }

    // ── Bono de proyecto ──────────────────────────────────────────────────────

    public function awardBonus($projectId)
    {
        $this->authorize('talento.projects.manage');

        try {
            $result = $this->bonusService->award((int)$projectId);
            return response()->json($result);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    // ── Tipos de actividad ────────────────────────────────────────────────────

    public function activityTypes()
    {
        $this->authorize('talento.activity_types.view');
        return response()->json(TalentoActivityType::orderBy('name')->get());
    }

    public function storeActivityType(Request $request)
    {
        $this->authorize('talento.activity_types.manage');

        $data = $request->validate([
            'name'            => 'required|string|max:120',
            'unit'            => 'required|string|max:40',
            'points_per_unit' => 'required|numeric|min:0',
            'money_per_unit'  => 'nullable|numeric|min:0',
            'active'          => 'boolean',
        ]);

        return response()->json(TalentoActivityType::create($data), 201);
    }

    public function updateActivityType(Request $request, $id)
    {
        $this->authorize('talento.activity_types.manage');

        $type = TalentoActivityType::findOrFail($id);

        $data = $request->validate([
            'name'            => 'sometimes|string|max:120',
            'unit'            => 'sometimes|string|max:40',
            'points_per_unit' => 'sometimes|numeric|min:0',
            'money_per_unit'  => 'nullable|numeric|min:0',
            'active'          => 'sometimes|boolean',
        ]);

        $type->update($data);
        return response()->json($type);
    }

    // ── Avance del colaborador en proyectos (endpoint para app/liquidación) ──

    public function colaboradorProgress(Request $request, $colaboradorId)
    {
        $this->authorize('talento.projects.view');

        $from = $request->from ?? now()->startOfWeek(\Carbon\Carbon::SATURDAY)->toDateString();
        $to   = $request->to   ?? \Carbon\Carbon::parse($from)->addDays(6)->toDateString();

        $points = $this->activityService->pointsForColaboradorInPeriod($colaboradorId, $from, $to);

        // Active projects where this collaborator has reports
        $projects = TalentoProject::whereHas('activities.reports.participants', fn($q) =>
            $q->where('colaborador_id', $colaboradorId)
        )->get(['id', 'name', 'status', 'end_date']);

        return response()->json([
            'colaborador_id'   => $colaboradorId,
            'period_start'     => $from,
            'period_end'       => $to,
            'external_points'  => round($points, 2),
            'projects'         => $projects,
        ]);
    }
}
