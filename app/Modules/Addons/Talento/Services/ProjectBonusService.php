<?php

namespace App\Modules\Addons\Talento\Services;

use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoLedgerEntry;
use App\Modules\Addons\Talento\Models\TalentoProject;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ProjectBonusService
{
    /**
     * Compute and award the project bonus.
     *
     * Progress:  total approved quantity / total planned quantity (across all activities).
     * Bonus:     computed via project.computeBonus(pct) — uses bonus_scale or flat amount.
     * Split:     PROPORTIONAL TO BASE SALARY of each participant who contributed.
     *            Participants = collaborators who have approved report_participants on this project.
     *
     * Writes ledger entries with concept='bonus'.
     * Returns array: [progress_pct, bonus_total, entries_written].
     */
    public function award(int $projectId): array
    {
        $project = TalentoProject::with('projectActivities.reports.participants')->findOrFail($projectId);

        // 1. Compute overall progress
        $totalPlanned  = 0.0;
        $totalApproved = 0.0;

        foreach ($project->projectActivities as $act) {
            $totalPlanned  += (float)$act->planned_quantity;
            foreach ($act->reports as $r) {
                if (in_array($r->status, ['approved', 'capped'])) {
                    $totalApproved += (float)$r->approved_quantity;
                }
            }
        }

        $pct        = $totalPlanned > 0 ? min(100.0, round($totalApproved / $totalPlanned * 100, 2)) : 0.0;
        $bonusTotal = $project->computeBonus($pct);

        if ($bonusTotal <= 0) {
            return ['progress_pct' => $pct, 'bonus_total' => 0.0, 'entries_written' => 0];
        }

        // 2. Identify participating collaborators and their base salaries
        $colaboradorIds = DB::table('talento_activity_report_participants as p')
            ->join('talento_project_activity_reports as r', 'r.id', '=', 'p.report_id')
            ->join('talento_project_activities as a', 'a.id', '=', 'r.project_activity_id')
            ->where('a.project_id', $projectId)
            ->whereIn('r.status', ['approved', 'capped'])
            ->distinct()
            ->pluck('p.colaborador_id')
            ->toArray();

        if (empty($colaboradorIds)) {
            return ['progress_pct' => $pct, 'bonus_total' => $bonusTotal, 'entries_written' => 0];
        }

        // Base salary from latest effective rule snapshot
        $salaries = [];
        $salarySum = 0.0;

        foreach ($colaboradorIds as $colId) {
            $snapshot = DB::table('talento_compensation_rule_history')
                ->where('colaborador_id', $colId)
                ->orderBy('assigned_at', 'desc')
                ->value('data');

            $base = $snapshot ? (float)json_decode($snapshot, true)['base_salary'] ?? 0 : 0.0;

            // Fallback: use colaborador.base_salary if no rule history
            if ($base <= 0) {
                $base = (float) DB::table('talento_colaboradores')->where('id', $colId)->value('base_salary') ?? 0.0;
            }

            $salaries[$colId] = $base;
            $salarySum       += $base;
        }

        // If everyone has 0 salary, split equally
        if ($salarySum <= 0) {
            $equal = round($bonusTotal / count($colaboradorIds), 2);
            foreach ($colaboradorIds as $colId) {
                $salaries[$colId] = $equal;
            }
            $salarySum = $bonusTotal;
        }

        // 3. Write ledger entries — mismo período que la liquidación (PayWeek, punto único).
        $w           = \App\Modules\Addons\Talento\Support\PayWeek::boundsFor(Carbon::now());
        $periodStart = $w['period_start'];
        $periodEnd   = $w['period_end'];
        $written     = 0;

        DB::transaction(function () use (
            $colaboradorIds, $salaries, $salarySum, $bonusTotal,
            $project, $pct, $periodStart, $periodEnd, &$written
        ) {
            foreach ($colaboradorIds as $colId) {
                $share = round(($salaries[$colId] / $salarySum) * $bonusTotal, 2);
                if ($share <= 0) continue;

                TalentoLedgerEntry::create([
                    'colaborador_id' => $colId,
                    'type'           => 'credit',
                    'concept'        => 'bonus',
                    'amount'         => $share,
                    'reference_type' => TalentoProject::class,
                    'reference_id'   => $project->id,
                    'period_start'   => $periodStart,
                    'period_end'     => $periodEnd,
                    'notes'          => "Bono proyecto «{$project->name}» — avance {$pct}%",
                    'created_by'     => auth()->id(),
                ]);

                $written++;
            }

            // Mark project as done
            $project->update(['status' => 'done']);
        });

        return [
            'progress_pct'   => $pct,
            'bonus_total'    => $bonusTotal,
            'entries_written'=> $written,
        ];
    }
}
