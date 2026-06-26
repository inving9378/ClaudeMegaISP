<?php

namespace App\Modules\Addons\PortalCliente\Support;

use App\Modules\Addons\MegaFamilia\Models\ParentalReward;
use App\Modules\Addons\MegaFamilia\Models\ParentalTaskAssignment;

/**
 * Balance de puntos por perfil (gamificación MegaFamilia).
 *
 *   balance = Σ(asignaciones completadas → task.points) − Σ(recompensas otorgadas.value)
 *
 * Las tareas son account-level y se reparten en parental_task_assignments; cada hijo
 * gana los puntos de SUS asignaciones en estado 'completed'.
 *
 * Recompensas (discriminador por falta de columnas source/status en parental_rewards):
 *   - Catálogo (el padre la creó): granted_at IS NULL.
 *   - Otorgada (canje aprobado):   granted_at IS NOT NULL  ← descuenta del balance.
 */
class MegaFamiliaBalance
{
    /** Mapa [profile_id => balance] para un set de perfiles (2 queries agregadas). */
    public static function forProfiles(array $profileIds): array
    {
        $profileIds = array_values(array_unique(array_map('intval', $profileIds)));
        if (empty($profileIds)) {
            return [];
        }

        $earned = ParentalTaskAssignment::query()
            ->join('parental_tasks', 'parental_tasks.id', '=', 'parental_task_assignments.task_id')
            ->whereIn('parental_task_assignments.profile_id', $profileIds)
            ->where('parental_task_assignments.status', 'completed')
            ->selectRaw('parental_task_assignments.profile_id as profile_id, COALESCE(SUM(parental_tasks.points),0) as total')
            ->groupBy('parental_task_assignments.profile_id')
            ->pluck('total', 'profile_id');

        $spent = ParentalReward::whereIn('profile_id', $profileIds)
            ->whereNotNull('granted_at')
            ->selectRaw('profile_id, COALESCE(SUM(value),0) as total')
            ->groupBy('profile_id')
            ->pluck('total', 'profile_id');

        $out = [];
        foreach ($profileIds as $id) {
            $out[$id] = (int) ($earned[$id] ?? 0) - (int) ($spent[$id] ?? 0);
        }

        return $out;
    }

    public static function forProfile(int $profileId): int
    {
        return self::forProfiles([$profileId])[$profileId] ?? 0;
    }
}
