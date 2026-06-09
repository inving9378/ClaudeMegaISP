<?php

namespace App\Modules\Addons\WarRoom\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Services\CompositeScoreService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DesempenoController extends Controller
{
    public function __construct(private CompositeScoreService $scoreService) {}

    public function show(Request $request): JsonResponse
    {
        if (!Schema::hasTable('talento_colaboradores')) {
            return response()->json(['available' => false]);
        }

        $from = Carbon::parse($request->input('from', now()->startOfMonth()->toDateString()))->startOfDay();
        $to   = Carbon::parse($request->input('to',   now()->toDateString()))->endOfDay();

        $rawIds = $request->input('ids');
        $colIds = $rawIds ? array_filter(explode(',', $rawIds), 'is_numeric') : null;

        $colaboradores = TalentoColaborador::with('user')
            ->where('status', 'active')
            ->when($colIds, fn($q) => $q->whereIn('id', $colIds))
            ->get();

        $weights = $this->loadWeights();

        $data = $colaboradores->map(fn($col) => $this->metricsForColaborador($col, $from, $to, $weights));

        return response()->json([
            'available'     => true,
            'from'          => $from->toDateString(),
            'to'            => $to->toDateString(),
            'colaboradores' => $data->values(),
        ]);
    }

    private function metricsForColaborador(TalentoColaborador $col, Carbon $from, Carbon $to, array $weights): array
    {
        $id = $col->id;

        // 1+2. OTs validadas (total + billable split) ─────────────────────────
        $ots = DB::table('talento_work_orders as wo')
            ->join('talento_work_order_types as t', 'wo.type_id', 't.id')
            ->where('wo.colaborador_id', $id)
            ->where('wo.status', 'validated')
            ->whereBetween('wo.validated_at', [$from, $to])
            ->selectRaw('COUNT(*) as cnt, SUM(wo.points) as pts,
                         SUM(t.is_billable) as billable_cnt,
                         SUM(CASE WHEN t.is_billable THEN wo.points ELSE 0 END) as billable_pts')
            ->first();

        // 3. Actividades de proyecto (points earned as participant) ────────────
        $actividadPts = (float) DB::table('talento_activity_report_participants as p')
            ->join('talento_project_activity_reports as r', 'p.report_id', 'r.id')
            ->where('p.colaborador_id', $id)
            ->whereIn('r.status', ['approved', 'capped'])
            ->whereBetween('r.report_date', [$from->toDateString(), $to->toDateString()])
            ->sum('p.points_earned');

        // 4. Asistencias ──────────────────────────────────────────────────────
        $asistencias = DB::table('talento_attendances')
            ->where('colaborador_id', $id)
            ->whereBetween('check_in_at', [$from, $to])
            ->count();

        // 5. Inspecciones de caja (FK = inspected_by) ────────────────────────
        $insp = DB::table('talento_caja_inspections')
            ->where('inspected_by', $id)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('COUNT(*) as total, SUM(overall_result = "pass") as passed')
            ->first();

        // 6. Bono salud de red ────────────────────────────────────────────────
        $bonos = DB::table('talento_health_bonus_log')
            ->where('colaborador_id', $id)
            ->whereBetween('checked_at', [$from, $to])
            ->selectRaw('COUNT(*) as total, SUM(bonus_awarded = 1) as eligible')
            ->first();

        // 7. Penalizaciones (applied o appealed = aún vigentes) ──────────────
        $penalizaciones = DB::table('talento_penalties')
            ->where('colaborador_id', $id)
            ->whereIn('status', ['applied', 'appealed'])
            ->whereBetween('created_at', [$from, $to])
            ->count();

        // 8. Desvíos de ruta ──────────────────────────────────────────────────
        $desvios = DB::table('talento_route_deviations')
            ->where('colaborador_id', $id)
            ->whereBetween('detected_at', [$from, $to])
            ->count();

        // 9. Incidencias de OT (via OT del colaborador) ───────────────────────
        $incidencias = DB::table('talento_work_order_incidents as i')
            ->join('talento_work_orders as wo', 'i.work_order_id', 'wo.id')
            ->where('wo.colaborador_id', $id)
            ->whereBetween('i.created_at', [$from, $to])
            ->count();

        // 10. Activaciones OLT confirmadas (via OT del colaborador) ───────────
        $activaciones = DB::table('talento_work_order_activations as a')
            ->join('talento_work_orders as wo', 'a.work_order_id', 'wo.id')
            ->where('wo.colaborador_id', $id)
            ->whereNotNull('a.activated_at')
            ->whereBetween('a.activated_at', [$from, $to])
            ->count();

        // 11. Extensiones de turno (via asistencia del colaborador) ───────────
        $extensiones = DB::table('talento_shift_extensions as e')
            ->join('talento_attendances as att', 'e.attendance_id', 'att.id')
            ->where('att.colaborador_id', $id)
            ->whereBetween('e.created_at', [$from, $to])
            ->count();

        // 12. Evaluaciones prácticas ──────────────────────────────────────────
        $evaluaciones = DB::table('talento_practical_evaluations')
            ->where('colaborador_id', $id)
            ->whereBetween('evaluated_at', [$from, $to])
            ->count();

        // Score compuesto: trailing N semanas desde hoy (CompositeScoreService
        // trabaja siempre hacia atrás desde now(); usamos el inicio del rango
        // para aproximar la ventana temporal).
        $weeksBack = max(1, (int) ceil($from->diffInDays(now()) / 7));
        $scoreData = $this->scoreService->scoreOne($col, $weights, $weeksBack);

        return [
            'colaborador_id' => $id,
            'name'           => $col->user?->name ?? '—',
            'level'          => DB::table('talento_levels')->find($col->level_id)?->name,
            'metrics'        => [
                'ots_validadas'      => ['count' => (int)($ots->cnt ?? 0),          'pts'      => (float)($ots->pts ?? 0)],
                'ots_billables'      => ['count' => (int)($ots->billable_cnt ?? 0), 'pts'      => (float)($ots->billable_pts ?? 0)],
                'actividad_proyecto' => (float) $actividadPts,
                'asistencias'        => (int) $asistencias,
                'inspecciones_caja'  => ['total'   => (int)($insp->total ?? 0),     'passed'   => (int)($insp->passed ?? 0)],
                'bonos_salud_red'    => ['total'   => (int)($bonos->total ?? 0),    'eligible' => (int)($bonos->eligible ?? 0)],
                'penalizaciones'     => (int) $penalizaciones,
                'desvios_ruta'       => (int) $desvios,
                'incidencias_ot'     => (int) $incidencias,
                'activaciones_olt'   => (int) $activaciones,
                'extensiones_turno'  => (int) $extensiones,
                'evaluaciones'       => (int) $evaluaciones,
            ],
            'score'      => $scoreData['total_score'],
            'stars'      => $scoreData['stars'],
            'components' => $scoreData['components'],
        ];
    }

    private function loadWeights(): array
    {
        $row     = DB::table('talento_escalafon_config')->where('id', 1)->first();
        $decoded = $row ? json_decode($row->weights, true) : null;
        return is_array($decoded) ? $decoded : CompositeScoreService::DEFAULT_WEIGHTS;
    }
}
