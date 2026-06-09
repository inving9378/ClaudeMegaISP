<?php

namespace App\Modules\Addons\WarRoom\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DesempenoSerieController extends Controller
{
    // ── Factor → {table, date_col, fk_col, extra_where}
    private const FACTORS = [
        'ots'              => ['talento_work_orders',                'validated_at',  'colaborador_id', ["status = 'validated'"]],
        'ots_pts'          => ['talento_work_orders',                'validated_at',  'colaborador_id', ["status = 'validated'"], 'sum_pts'],
        'asistencias'      => ['talento_attendances',                'check_in_at',   'colaborador_id', []],
        'inspecciones_caja'=> ['talento_caja_inspections',           'created_at',    'inspected_by',   []],
        'bonos_salud_red'  => ['talento_health_bonus_log',           'checked_at',    'colaborador_id', ['bonus_awarded = 1']],
        'penalizaciones'   => ['talento_penalties',                  'created_at',    'colaborador_id', ["status IN ('applied','appealed')"]],
        'desvios_ruta'     => ['talento_route_deviations',           'detected_at',   'colaborador_id', []],
        'incidencias_ot'   => null, // join needed — handled separately
        'activaciones_olt' => null, // join needed — handled separately
        'extensiones_turno'=> null, // join needed — handled separately
        'evaluaciones'     => ['talento_practical_evaluations',      'evaluated_at',  'colaborador_id', []],
    ];

    public function show(Request $request): JsonResponse
    {
        if (!Schema::hasTable('talento_colaboradores')) {
            return response()->json(['available' => false]);
        }

        $department   = $request->input('department', '');
        $factor       = $request->input('factor', 'ots');
        $granularidad = $request->input('granularidad', 'semana');
        $from = Carbon::parse($request->input('from', now()->startOfMonth()->toDateString()))->startOfDay();
        $to   = Carbon::parse($request->input('to',   now()->toDateString()))->endOfDay();

        // Colaboradores activos del department
        $query = TalentoColaborador::with('user')->where('status', 'active');
        if ($department === '__none__') {
            $query->whereNull('department');
        } elseif ($department !== '') {
            $query->where('department', $department);
        }
        $colaboradores = $query->get();

        if ($colaboradores->isEmpty()) {
            return response()->json([
                'available'     => true,
                'labels'        => [],
                'series'        => [],
                'from'          => $from->toDateString(),
                'to'            => $to->toDateString(),
                'granularidad'  => $granularidad,
            ]);
        }

        // Labels del período
        $labels = $this->buildLabels($from, $to, $granularidad);
        $labelIndex = array_flip($labels);

        $colIds = $colaboradores->pluck('id')->all();

        // Obtener datos según el factor
        if ($factor === 'total') {
            $rawData = $this->queryTotal($colIds, $from, $to, $granularidad);
        } else {
            $rawData = $this->queryFactor($factor, $colIds, $from, $to, $granularidad);
        }

        // Construir series alineadas a labels
        $series = [];
        foreach ($colaboradores as $col) {
            $datos = array_fill(0, count($labels), 0);
            $colData = $rawData[$col->id] ?? [];
            foreach ($colData as $label => $value) {
                $idx = $labelIndex[$label] ?? null;
                if ($idx !== null) {
                    $datos[$idx] = $value;
                }
            }
            $series[] = [
                'colaborador_id' => $col->id,
                'nombre'         => $col->user?->name ?? '—',
                'datos'          => $datos,
            ];
        }

        return response()->json([
            'available'    => true,
            'labels'       => $labels,
            'series'       => $series,
            'from'         => $from->toDateString(),
            'to'           => $to->toDateString(),
            'granularidad' => $granularidad,
        ]);
    }

    // ── Generador de labels del período ──────────────────────────────────────

    private function buildLabels(Carbon $from, Carbon $to, string $granularidad): array
    {
        $labels = [];
        $cursor = $from->copy();

        match ($granularidad) {
            'dia'    => $step = fn($c) => $c->addDay(),
            'semana' => $step = fn($c) => $c->addWeek(),
            'mes'    => $step = fn($c) => $c->addMonth(),
            'ano'    => $step = fn($c) => $c->addYear(),
            default  => $step = fn($c) => $c->addWeek(),
        };

        // Normalizar al inicio del período para el cursor
        if ($granularidad === 'semana') $cursor = $cursor->copy()->startOfWeek(Carbon::MONDAY);
        if ($granularidad === 'mes')    $cursor = $cursor->copy()->startOfMonth();
        if ($granularidad === 'ano')    $cursor = $cursor->copy()->startOfYear();

        while ($cursor->lte($to)) {
            $labels[] = $this->formatLabel($cursor, $granularidad);
            $step($cursor);
        }

        return $labels;
    }

    private function formatLabel(Carbon $dt, string $granularidad): string
    {
        return match ($granularidad) {
            'dia'    => $dt->format('Y-m-d'),
            'semana' => $dt->copy()->startOfWeek(Carbon::MONDAY)->format('Y-m-d'),
            'mes'    => $dt->format('Y-m'),
            'ano'    => $dt->format('Y'),
            default  => $dt->format('Y-m-d'),
        };
    }

    private function periodExpr(string $alias, string $col, string $granularidad): string
    {
        $c = "`{$alias}`.`{$col}`";
        return match ($granularidad) {
            'dia'    => "DATE({$c})",
            'semana' => "DATE_FORMAT(DATE_SUB({$c}, INTERVAL (WEEKDAY({$c})) DAY), '%Y-%m-%d')",
            'mes'    => "DATE_FORMAT({$c}, '%Y-%m')",
            'ano'    => "DATE_FORMAT({$c}, '%Y')",
            default  => "DATE({$c})",
        };
    }

    // ── Query genérica para factores simples ─────────────────────────────────

    private function queryFactor(string $factor, array $colIds, Carbon $from, Carbon $to, string $granularidad): array
    {
        $def = self::FACTORS[$factor] ?? null;
        if ($def === null) {
            // Factores con JOIN
            return $this->queryJoinFactor($factor, $colIds, $from, $to, $granularidad);
        }

        [$table, $dateCol, $fkCol, $where] = $def;
        $aggMode = $def[4] ?? 'count';

        if (!Schema::hasTable($table)) return [];

        $periodExpr = $this->periodExpr('t', $dateCol, $granularidad);
        $aggExpr    = $aggMode === 'sum_pts' ? 'SUM(`t`.`points`)' : 'COUNT(*)';

        $q = DB::table("{$table} as t")
            ->whereIn("t.{$fkCol}", $colIds)
            ->whereBetween("t.{$dateCol}", [$from, $to])
            ->selectRaw("`t`.`{$fkCol}` as col_id, {$periodExpr} as periodo, {$aggExpr} as valor")
            ->groupByRaw("`t`.`{$fkCol}`, {$periodExpr}");

        foreach ($where as $w) {
            $q->whereRaw($w);
        }

        return $this->pivotRows($q->get());
    }

    private function queryJoinFactor(string $factor, array $colIds, Carbon $from, Carbon $to, string $granularidad): array
    {
        if (!Schema::hasTable('talento_work_orders')) return [];

        return match ($factor) {
            'incidencias_ot' => $this->queryJoin(
                'talento_work_order_incidents as i',
                'talento_work_orders as wo', 'i.work_order_id', 'wo.id',
                'i.created_at', 'wo.colaborador_id',
                $colIds, $from, $to, $granularidad
            ),
            'activaciones_olt' => $this->queryJoin(
                'talento_work_order_activations as a',
                'talento_work_orders as wo', 'a.work_order_id', 'wo.id',
                'a.activated_at', 'wo.colaborador_id',
                $colIds, $from, $to, $granularidad,
                ['a.activated_at IS NOT NULL']
            ),
            'extensiones_turno' => $this->queryJoin(
                'talento_shift_extensions as e',
                'talento_attendances as att', 'e.attendance_id', 'att.id',
                'e.created_at', 'att.colaborador_id',
                $colIds, $from, $to, $granularidad
            ),
            default => [],
        };
    }

    private function queryJoin(
        string $mainTable, string $joinTable, string $joinOn, string $joinTarget,
        string $dateCol, string $fkCol,
        array $colIds, Carbon $from, Carbon $to, string $granularidad,
        array $extraWhere = []
    ): array {
        [$mainAlias] = explode(' as ', $mainTable);
        [$joinAlias] = explode(' as ', $joinTable);
        [$mainAliasClean] = explode(' as ', $mainTable . ' ');

        // Build alias for expressions
        $mainA = trim(explode('as', $mainTable)[1] ?? $mainTable);
        $joinA = trim(explode('as', $joinTable)[1] ?? $joinTable);

        $periodExpr = $this->periodExpr($mainA, explode('.', $dateCol)[1], $granularidad);
        $fkColClean = explode('.', $fkCol)[1];

        $q = DB::table($mainTable)
            ->join($joinTable, $joinOn, '=', $joinTarget)
            ->whereIn("{$joinA}.{$fkColClean}", $colIds)
            ->whereBetween($dateCol, [$from, $to])
            ->selectRaw("`{$joinA}`.`{$fkColClean}` as col_id, {$periodExpr} as periodo, COUNT(*) as valor")
            ->groupByRaw("`{$joinA}`.`{$fkColClean}`, {$periodExpr}");

        foreach ($extraWhere as $w) {
            $q->whereRaw($w);
        }

        return $this->pivotRows($q->get());
    }

    // ── Total: agrega los factores positivos ─────────────────────────────────

    private function queryTotal(array $colIds, Carbon $from, Carbon $to, string $granularidad): array
    {
        $positives = ['ots', 'asistencias', 'inspecciones_caja', 'bonos_salud_red',
                      'activaciones_olt', 'evaluaciones', 'incidencias_ot']; // incidencias como actividad de campo

        $accumulated = [];
        foreach ($positives as $f) {
            $partial = $this->queryFactor($f, $colIds, $from, $to, $granularidad);
            foreach ($partial as $colId => $periodos) {
                foreach ($periodos as $periodo => $valor) {
                    $accumulated[$colId][$periodo] = ($accumulated[$colId][$periodo] ?? 0) + $valor;
                }
            }
        }
        return $accumulated;
    }

    // ── Pivot de rows DB a [colId => [periodo => valor]] ─────────────────────

    private function pivotRows($rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            $result[$row->col_id][$row->periodo] = (int) $row->valor;
        }
        return $result;
    }
}
