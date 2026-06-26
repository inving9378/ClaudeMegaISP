<?php

namespace App\Modules\Addons\PortalCliente\Support;

use App\Modules\Addons\MegaFamilia\Models\ParentalReward;
use App\Modules\Addons\MegaFamilia\Models\ParentalTask;

/**
 * Balance de puntos por perfil (gamificación MegaFamilia).
 *
 *   balance = Σ(tareas completadas.points) − Σ(recompensas otorgadas.value)
 *
 * Discriminador (acordado por falta de columnas source/status en parental_rewards):
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

        $earned = ParentalTask::whereIn('profile_id', $profileIds)
            ->where('status', 'completed')
            ->selectRaw('profile_id, COALESCE(SUM(points),0) as total')
            ->groupBy('profile_id')
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
