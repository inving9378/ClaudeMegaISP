<?php

namespace App\Modules\Addons\Talento\Services;

use App\Modules\Addons\Talento\Models\TalentoAttendance;
use App\Modules\Addons\Talento\Models\TalentoCajaInspection;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoCompensationRuleHistory;
use App\Modules\Addons\Talento\Models\TalentoHealthBonusLog;
use App\Modules\Addons\Talento\Models\TalentoPenalty;
use App\Modules\Addons\Talento\Models\TalentoWorkOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CompositeScoreService
{
    // Default weights (sum = 100)
    public const DEFAULT_WEIGHTS = [
        'quota'        => 35,   // % of weekly quota achieved (avg last 4 weeks)
        'quality'      => 25,   // caja inspection pass rate
        'health_bonus' => 15,   // health bonus eligibility rate
        'normas'       => 15,   // inverse penalty rate (fewer = better)
        'attendance'   => 10,   // attendance rate
    ];

    public function __construct(private ProjectActivityService $projectSvc) {}

    public function scoreAll(array $weights = [], int $weeksBack = 4): array
    {
        $weights = $this->mergeWeights($weights);
        $colaboradores = TalentoColaborador::with(['user', 'level'])
            ->where('status', 'active')
            ->get();

        $scores = $colaboradores->map(fn($c) => $this->scoreOne($c, $weights, $weeksBack))->values();

        // Sort descending by total score
        $sorted = $scores->sortByDesc('total_score')->values();

        // Add rank + "most improved" delta (compare last 2 weeks vs prior 2 weeks)
        $improved = $this->mostImproved($colaboradores->pluck('id')->all(), $weights);

        return [
            'weights'       => $weights,
            'weeks_back'    => $weeksBack,
            'ranking'       => $sorted->map(function ($s, $idx) use ($improved) {
                $s['rank'] = $idx + 1;
                $s['improvement_delta'] = $improved[$s['colaborador_id']] ?? null;
                return $s;
            })->values(),
        ];
    }

    public function scoreOne(TalentoColaborador $col, array $weights = [], int $weeksBack = 4): array
    {
        $weights = $this->mergeWeights($weights);
        $end   = Carbon::now()->endOfDay();
        $start = Carbon::now()->subWeeks($weeksBack)->startOfDay();

        $components = [
            'quota'        => $this->quotaScore($col->id, $weeksBack),
            'quality'      => $this->qualityScore($col->id, $start, $end),
            'health_bonus' => $this->healthBonusScore($col->id, $weeksBack),
            'normas'       => $this->normasScore($col->id, $start, $end),
            'attendance'   => $this->attendanceScore($col->id, $start, $end),
        ];

        $totalScore = 0.0;
        foreach ($components as $key => $value) {
            $totalScore += ($value / 100) * ($weights[$key] / 100) * 100;
        }
        $totalScore = round($totalScore, 1);

        return [
            'colaborador_id' => $col->id,
            'name'           => $col->user?->name ?? '—',
            'level'          => $col->level?->name,
            'total_score'    => $totalScore,
            'stars'          => $this->starsFromScore($totalScore),
            'components'     => array_map(fn($v) => round($v, 1), $components),
        ];
    }

    // ── Component scorers (0-100) ─────────────────────────────────────────

    private function quotaScore(int $colId, int $weeksBack): float
    {
        $ruleSnapshot = TalentoCompensationRuleHistory::where('colaborador_id', $colId)
            ->orderByDesc('assigned_at')->value('data');
        $weeklyQuota = $ruleSnapshot ? (int)($ruleSnapshot['weekly_quota_units'] ?? 0) : 0;

        if ($weeklyQuota === 0) return 0.0;

        $pcts = [];
        for ($i = 0; $i < $weeksBack; $i++) {
            $wStart = Carbon::now()->subWeeks($i + 1)->startOfDay();
            $wEnd   = Carbon::now()->subWeeks($i)->endOfDay();

            $internal = (int) TalentoWorkOrder::where('colaborador_id', $colId)
                ->validatedBillable()
                ->whereBetween('validated_at', [$wStart, $wEnd])
                ->sum('points');

            $external = (int) $this->projectSvc->pointsForColaboradorInPeriod($colId, $wStart, $wEnd);

            $pcts[] = min(($internal + $external) / $weeklyQuota, 1.0);
        }

        return count($pcts) > 0 ? round((array_sum($pcts) / count($pcts)) * 100, 1) : 0.0;
    }

    private function qualityScore(int $colId, Carbon $start, Carbon $end): float
    {
        $total = TalentoCajaInspection::where('inspected_by', $colId)
            ->whereBetween('created_at', [$start, $end])
            ->count();

        if ($total === 0) return 100.0; // no inspections = no failures

        $passed = TalentoCajaInspection::where('inspected_by', $colId)
            ->whereBetween('created_at', [$start, $end])
            ->where('overall_result', 'pass')
            ->count();

        return round(($passed / $total) * 100, 1);
    }

    private function healthBonusScore(int $colId, int $weeksBack): float
    {
        $total = TalentoHealthBonusLog::where('colaborador_id', $colId)
            ->where('checked_at', '>=', Carbon::now()->subWeeks($weeksBack))
            ->count();

        if ($total === 0) return 0.0;

        $eligible = TalentoHealthBonusLog::where('colaborador_id', $colId)
            ->where('checked_at', '>=', Carbon::now()->subWeeks($weeksBack))
            ->where('bonus_awarded', true)
            ->count();

        return round(($eligible / $total) * 100, 1);
    }

    private function normasScore(int $colId, Carbon $start, Carbon $end): float
    {
        // Fewer active penalties = better. Cap at 5 penalties = 0 score.
        $penaltyCount = TalentoPenalty::where('colaborador_id', $colId)
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('status', ['applied', 'appealed'])
            ->count();

        return max(0.0, round((1 - min($penaltyCount, 5) / 5) * 100, 1));
    }

    private function attendanceScore(int $colId, Carbon $start, Carbon $end): float
    {
        $expectedDays = $start->diffInWeekdays($end);
        if ($expectedDays === 0) return 0.0;

        $checkedIn = TalentoAttendance::where('colaborador_id', $colId)
            ->whereBetween('check_in_at', [$start, $end])
            ->count();

        return round(min($checkedIn / $expectedDays, 1.0) * 100, 1);
    }

    // ── Most improved (current 2w vs prior 2w) ───────────────────────────

    private function mostImproved(array $colIds, array $weights): array
    {
        $now       = Carbon::now();
        $midPoint  = $now->copy()->subWeeks(2);
        $fourBack  = $now->copy()->subWeeks(4);

        $result = [];
        foreach ($colIds as $id) {
            $col = TalentoColaborador::with(['user', 'level'])->find($id);
            if (!$col) continue;

            $recent = $this->scoreOne($col, $weights, 2)['total_score'];
            $prior  = $this->scoreOnePeriod($col, $weights, $fourBack, $midPoint)['total_score'];

            $result[$id] = round($recent - $prior, 1);
        }

        return $result;
    }

    private function scoreOnePeriod(TalentoColaborador $col, array $weights, Carbon $start, Carbon $end): array
    {
        $weeksBack = max(1, (int) $start->diffInWeeks($end));
        return $this->scoreOne($col, $weights, $weeksBack);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function starsFromScore(float $score): int
    {
        if ($score >= 90) return 5;
        if ($score >= 75) return 4;
        if ($score >= 55) return 3;
        if ($score >= 35) return 2;
        if ($score >= 15) return 1;
        return 0;
    }

    private function mergeWeights(array $override): array
    {
        $merged = array_merge(self::DEFAULT_WEIGHTS, $override);
        // Normalize to sum = 100
        $total = array_sum($merged);
        if ($total === 0) return self::DEFAULT_WEIGHTS;
        foreach ($merged as $k => $v) {
            $merged[$k] = round($v / $total * 100, 1);
        }
        return $merged;
    }
}
