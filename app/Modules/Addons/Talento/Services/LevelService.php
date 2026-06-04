<?php

namespace App\Modules\Addons\Talento\Services;

use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoCompensationRule;
use App\Modules\Addons\Talento\Models\TalentoCompensationRuleHistory;
use App\Modules\Addons\Talento\Models\TalentoLevel;
use App\Modules\Addons\Talento\Models\TalentoLevelAssignment;
use Illuminate\Support\Facades\DB;

class LevelService
{
    public function __construct(private CertificationService $certSvc) {}

    /**
     * Calculate the highest level a collaborator qualifies for.
     * Returns ['eligible_level' => TalentoLevel|null, 'progress' => [...], 'levels' => [...]]
     */
    public function eligibility(int $colaboradorId): array
    {
        $colaborador = TalentoColaborador::findOrFail($colaboradorId);
        $progress    = $this->certSvc->progressForColaborador($colaboradorId);

        $levels = TalentoLevel::where('active', true)->orderBy('rank')->get();

        $eligibleLevel = null;
        $levelsWithStatus = [];

        foreach ($levels as $level) {
            $qualifies = $level->isSatisfiedBy($progress);
            $levelsWithStatus[] = [
                'id'                      => $level->id,
                'name'                    => $level->name,
                'rank'                    => $level->rank,
                'base_salary'             => $level->base_salary,
                'required_certifications' => $level->required_certifications,
                'qualifies'               => $qualifies,
                'is_current'              => $colaborador->level_id === $level->id,
            ];
            if ($qualifies) {
                $eligibleLevel = $level;
            }
        }

        return [
            'colaborador_id' => $colaboradorId,
            'current_level'  => $colaborador->level_id
                ? TalentoLevel::find($colaborador->level_id)
                : null,
            'eligible_level' => $eligibleLevel,
            'can_promote'    => $eligibleLevel && $eligibleLevel->id !== $colaborador->level_id
                             && ($eligibleLevel->rank > ($colaborador->level?->rank ?? 0)
                                 || $colaborador->level_id === null),
            'progress'       => $progress,
            'levels'         => $levelsWithStatus,
        ];
    }

    /**
     * Promote a collaborator to a level.
     * - Updates colaborador.level_id
     * - Records talento_level_assignments
     * - Writes a new talento_compensation_rule_history entry with the level's base_salary
     *   (reuses the existing mechanism — NOT a parallel salary source)
     */
    public function promote(
        int    $colaboradorId,
        int    $levelId,
        string $reason = 'promotion',
        ?int   $assignedBy = null
    ): TalentoLevelAssignment {
        $colaborador = TalentoColaborador::findOrFail($colaboradorId);
        $level       = TalentoLevel::findOrFail($levelId);
        $progress    = $this->certSvc->progressForColaborador($colaboradorId);

        return DB::transaction(function () use ($colaborador, $level, $reason, $assignedBy, $progress) {
            // 1. Update current level on the collaborator
            $colaborador->update(['level_id' => $level->id]);

            // 2. Record the level assignment
            $assignment = TalentoLevelAssignment::create([
                'colaborador_id'          => $colaborador->id,
                'level_id'                => $level->id,
                'reason'                  => $reason,
                'certifications_snapshot' => $progress,
                'assigned_by'             => $assignedBy,
                'assigned_at'             => now(),
                'created_at'              => now(),
            ]);

            // 3. Apply base_salary via existing compensation history mechanism
            //    Take the latest snapshot and override base_salary only
            $latestHistory = TalentoCompensationRuleHistory::where('colaborador_id', $colaborador->id)
                ->orderByDesc('assigned_at')
                ->first();

            $snapshot = $latestHistory ? (array) $latestHistory->data : [
                'base_salary'        => $level->base_salary,
                'weekly_quota_units' => 0,
                'period'             => 'weekly',
            ];

            $snapshot['base_salary'] = (float) $level->base_salary;
            $snapshot['_level_id']   = $level->id;
            $snapshot['_level_name'] = $level->name;
            $snapshot['_promoted_at']= now()->toISOString();

            $ruleId = $latestHistory ? $latestHistory->rule_id : null;

            TalentoCompensationRuleHistory::create([
                'colaborador_id' => $colaborador->id,
                'rule_id'        => $ruleId,
                'data'           => $snapshot,
                'assigned_at'    => now(),
            ]);

            return $assignment->load('level');
        });
    }

    /**
     * Check if a collaborator's level rank satisfies a required level.
     * Returns true if collaborator rank >= required rank, or if no level required.
     */
    public function satisfiesRequirement(?int $colaboradorLevelId, ?int $requiredLevelId): bool
    {
        if ($requiredLevelId === null) return true;
        if ($colaboradorLevelId === null) return false;

        $colaboradorLevel = TalentoLevel::find($colaboradorLevelId);
        $requiredLevel    = TalentoLevel::find($requiredLevelId);

        if (!$colaboradorLevel || !$requiredLevel) return false;

        return $colaboradorLevel->rank >= $requiredLevel->rank;
    }
}
