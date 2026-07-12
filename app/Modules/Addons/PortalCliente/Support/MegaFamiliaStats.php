<?php

namespace App\Modules\Addons\PortalCliente\Support;

use App\Modules\Addons\MegaFamilia\Models\ParentalEvent;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Estadísticas de uso MegaFamilia para el portal de cliente (solo lectura).
 *
 * Réplica SCOPEADA de la agregación de `MegaFamilia\Controllers\ReportesController`
 * (panel admin, sin tenant scoping): aquí toda query exige `whereIn('profile_id', $profileIds)`
 * con el set de perfiles YA verificado como propiedad del cliente (ver MegaFamiliaController::index,
 * que deriva $profileIds desde ParentalAccount::forClient()). Un array vacío ⇒ resultados vacíos,
 * nunca "todos los eventos" (fail-closed).
 */
class MegaFamiliaStats
{
    public static function forProfiles(array $profileIds, int $days = 14): array
    {
        $profileIds = array_values(array_unique(array_map('intval', $profileIds)));
        $to   = Carbon::now()->endOfDay();
        $from = Carbon::now()->subDays(max(1, $days) - 1)->startOfDay();

        if (empty($profileIds)) {
            return [
                'range'              => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
                'kpis'               => ['total_screen_minutes' => 0, 'top_app' => '—', 'blocked_attempts' => 0],
                'screen_time_by_day' => collect(),
                'top_apps'           => collect(),
                'activity_by_hour'   => collect(),
                'blocked_sites'      => collect(),
            ];
        }

        $screenByDay  = self::screenTimeByDay($profileIds, $from, $to);
        $topApps      = self::topApps($profileIds, $from, $to);
        $blockedSites = self::blockedSites($profileIds, $from, $to);

        return [
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'kpis'  => [
                'total_screen_minutes' => (int) $screenByDay->sum('minutes'),
                'top_app'              => $topApps->first()['app'] ?? '—',
                'blocked_attempts'     => (int) $blockedSites->sum('attempts'),
            ],
            'screen_time_by_day' => $screenByDay,
            'top_apps'           => $topApps,
            'activity_by_hour'   => self::activityByHour($profileIds, $from, $to),
            'blocked_sites'      => $blockedSites,
        ];
    }

    private static function screenTimeByDay(array $profileIds, Carbon $from, Carbon $to): Collection
    {
        $events = ParentalEvent::query()
            ->where('action', 'screen_time')
            ->whereIn('profile_id', $profileIds)
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at')
            ->get(['created_at', 'detail']);

        $bucket = [];
        foreach ($events as $e) {
            $day = $e->created_at->format('Y-m-d');
            $bucket[$day] = ($bucket[$day] ?? 0) + self::extractMinutes($e->detail);
        }

        $rows = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $k = $d->format('Y-m-d');
            $rows[] = ['day' => $k, 'minutes' => $bucket[$k] ?? 0];
        }

        return collect($rows);
    }

    private static function topApps(array $profileIds, Carbon $from, Carbon $to): Collection
    {
        $events = ParentalEvent::query()
            ->where('action', 'app_usage')
            ->whereIn('profile_id', $profileIds)
            ->whereBetween('created_at', [$from, $to])
            ->get(['detail']);

        $totals = [];
        foreach ($events as $e) {
            $parsed = self::parseDetail($e->detail);
            $app    = $parsed['app'] ?? $parsed['app_name'] ?? (is_string($e->detail) ? trim($e->detail) : null);
            if (! $app) {
                continue;
            }
            $totals[$app] = ($totals[$app] ?? 0) + self::extractMinutes($e->detail);
        }
        arsort($totals);
        $top   = array_slice($totals, 0, 10, true);
        $grand = array_sum($totals) ?: 1;

        return collect($top)->map(fn ($mins, $app) => [
            'app'     => $app,
            'minutes' => (int) $mins,
            'percent' => round(($mins / $grand) * 100, 1),
        ])->values();
    }

    private static function activityByHour(array $profileIds, Carbon $from, Carbon $to): Collection
    {
        $rows = ParentalEvent::query()
            ->whereIn('profile_id', $profileIds)
            ->whereBetween('created_at', [$from, $to])
            ->select(DB::raw('HOUR(created_at) as hour'), DB::raw('COUNT(*) as total'))
            ->groupBy('hour')
            ->get()
            ->keyBy('hour');

        $out = [];
        for ($h = 0; $h < 24; $h++) {
            $out[] = ['hour' => $h, 'total' => (int) ($rows[$h]->total ?? 0)];
        }

        return collect($out);
    }

    private static function blockedSites(array $profileIds, Carbon $from, Carbon $to): Collection
    {
        $events = ParentalEvent::query()
            ->where('action', 'web_blocked')
            ->whereIn('profile_id', $profileIds)
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at')
            ->get(['detail', 'created_at']);

        $totals = [];
        foreach ($events as $e) {
            $parsed = self::parseDetail($e->detail);
            $domain = $parsed['domain'] ?? $parsed['url'] ?? (is_string($e->detail) ? trim($e->detail) : null);
            if (! $domain) {
                continue;
            }
            if (! isset($totals[$domain])) {
                $totals[$domain] = ['attempts' => 0, 'last_at' => $e->created_at];
            }
            $totals[$domain]['attempts']++;
            if ($e->created_at->gt($totals[$domain]['last_at'])) {
                $totals[$domain]['last_at'] = $e->created_at;
            }
        }
        uasort($totals, fn ($a, $b) => $b['attempts'] <=> $a['attempts']);

        return collect($totals)->take(15)->map(fn ($r, $domain) => [
            'domain'   => $domain,
            'attempts' => $r['attempts'],
            'last_at'  => $r['last_at']->toDateTimeString(),
        ])->values();
    }

    private static function parseDetail(?string $detail): array
    {
        if (! $detail) {
            return [];
        }
        $j = json_decode($detail, true);

        return is_array($j) ? $j : [];
    }

    private static function extractMinutes($detail): int
    {
        if (is_numeric($detail)) {
            return (int) $detail;
        }
        $p = self::parseDetail($detail);
        if (isset($p['minutes'])) {
            return (int) $p['minutes'];
        }
        if (isset($p['duration'])) {
            return (int) $p['duration'];
        }

        return 1;
    }
}
