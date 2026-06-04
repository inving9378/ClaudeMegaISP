<?php

namespace App\Modules\Addons\Talento\Services;

use App\Modules\Addons\Talento\Models\TalentoActivityReportParticipant;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoProject;
use App\Modules\Addons\Talento\Models\TalentoProjectActivity;
use App\Modules\Addons\Talento\Models\TalentoProjectActivityReport;
use Illuminate\Support\Facades\DB;

class ProjectActivityService
{
    /**
     * Submit (and immediately approve) an activity report.
     *
     * Anti-encimar rule: if submitted quantity + already approved > planned_quantity,
     *   the report is capped to the remaining capacity; excess is rejected with a note.
     *
     * 1/N split: approved_quantity ÷ N × points_per_unit = each participant's points.
     *
     * @param  int    $projectActivityId
     * @param  float  $quantity            Raw quantity submitted
     * @param  string $reportDate
     * @param  int[]  $colaboradorIds      Participant collaborator IDs
     * @param  string|null $notes
     * @return TalentoProjectActivityReport
     */
    public function submitReport(
        int    $projectActivityId,
        float  $quantity,
        string $reportDate,
        array  $colaboradorIds,
        ?string $notes = null
    ): TalentoProjectActivityReport {
        if (empty($colaboradorIds)) {
            throw new \InvalidArgumentException('Debe haber al menos un participante.');
        }

        $activity     = TalentoProjectActivity::with('activityType')->findOrFail($projectActivityId);
        $ppu          = (float)$activity->activityType->points_per_unit;
        $remaining    = $activity->remaining(); // anti-encimar guard

        if ($remaining <= 0) {
            throw new \RuntimeException("Esta actividad ya alcanzó su techo planeado ({$activity->planned_quantity} {$activity->activityType->unit}). No se puede reportar más.");
        }

        // Determine approved quantity (cap at remaining)
        $approvedQty = min($quantity, $remaining);
        $status      = $approvedQty < $quantity ? 'capped' : 'approved';
        $capNote     = $status === 'capped'
            ? " [Excedente {$activity->activityType->unit} recortado: se aprobaron {$approvedQty} de {$quantity} solicitados]"
            : '';

        return DB::transaction(function () use (
            $activity, $quantity, $approvedQty, $status, $reportDate,
            $colaboradorIds, $notes, $capNote, $ppu
        ) {
            $report = TalentoProjectActivityReport::create([
                'project_activity_id' => $activity->id,
                'reported_by'         => auth()->id(),
                'quantity'            => $quantity,
                'report_date'         => $reportDate,
                'status'              => $status,
                'approved_quantity'   => $approvedQty,
                'notes'               => ($notes ?? '') . $capNote,
                'created_by'          => auth()->id(),
            ]);

            // 1/N split
            $n         = count($colaboradorIds);
            $shareEach = round($approvedQty / $n, 4);
            $ptEach    = round($shareEach * $ppu, 4);

            foreach (array_unique($colaboradorIds) as $colId) {
                TalentoActivityReportParticipant::create([
                    'report_id'      => $report->id,
                    'colaborador_id' => $colId,
                    'quantity_share' => $shareEach,
                    'points_earned'  => $ptEach,
                ]);
            }

            return $report->load('participants.colaborador.user');
        });
    }

    /**
     * Approve a pending report (explicit approval flow — optional, reports can be auto-approved).
     */
    public function approve(int $reportId): TalentoProjectActivityReport
    {
        $report = TalentoProjectActivityReport::with('projectActivity.activityType', 'participants')
            ->findOrFail($reportId);

        if ($report->status !== 'pending') {
            throw new \RuntimeException("Solo se pueden aprobar reportes en estado pendiente.");
        }

        $activity  = $report->projectActivity;
        $remaining = $activity->remaining();
        $approved  = min((float)$report->quantity, $remaining);
        $status    = $approved < (float)$report->quantity ? 'capped' : 'approved';
        $ppu       = (float)$activity->activityType->points_per_unit;

        return DB::transaction(function () use ($report, $approved, $status, $ppu) {
            $report->update(['status' => $status, 'approved_quantity' => $approved, 'updated_by' => auth()->id()]);

            $n = $report->participants()->count();
            if ($n > 0) {
                $shareEach = round($approved / $n, 4);
                $ptEach    = round($shareEach * $ppu, 4);
                $report->participants()->update([
                    'quantity_share' => $shareEach,
                    'points_earned'  => $ptEach,
                ]);
            }

            return $report->fresh();
        });
    }

    /**
     * Points earned by a collaborator for a project activity across approved/capped reports.
     */
    public function pointsForColaborador(int $colaboradorId, ?int $projectId = null): float
    {
        $q = TalentoActivityReportParticipant::where('colaborador_id', $colaboradorId);

        if ($projectId) {
            $q->whereHas('report.projectActivity', fn($r) => $r->where('project_id', $projectId));
        }

        return (float) $q->whereHas('report', fn($r) =>
            $r->whereIn('status', ['approved', 'capped'])
        )->sum('points_earned');
    }

    /**
     * Points earned by a collaborator for all activities in a date range.
     * Used by LiquidationService extension.
     */
    public function pointsForColaboradorInPeriod(int $colaboradorId, string $startDate, string $endDate): float
    {
        return (float) TalentoActivityReportParticipant::where('colaborador_id', $colaboradorId)
            ->whereHas('report', fn($r) =>
                $r->whereIn('status', ['approved', 'capped'])
                  ->whereBetween('report_date', [$startDate, $endDate])
            )
            ->sum('points_earned');
    }
}
