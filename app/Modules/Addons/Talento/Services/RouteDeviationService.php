<?php

namespace App\Modules\Addons\Talento\Services;

use App\Modules\Addons\Talento\Models\TalentoLocationPing;
use App\Modules\Addons\Talento\Models\TalentoRoute;
use App\Modules\Addons\Talento\Models\TalentoRouteDeviation;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class RouteDeviationService
{
    /**
     * Corridor half-width in meters — GPS error absorption.
     * A ping must be this far from EVERY stop to count as off-corridor.
     */
    private const CORRIDOR_M    = 300;

    /**
     * Minimum consecutive off-corridor minutes before alerting supervisor.
     * Filters out brief detours (bathroom stop, traffic diversion, GPS jitter).
     */
    private const MIN_SUSTAINED = 8;

    /**
     * Analyze pings for a route and detect sustained deviations.
     * Returns array of new TalentoRouteDeviation records created.
     */
    public function analyze(TalentoRoute $route): array
    {
        $stops = $route->stops()->with('workOrder')->get();
        if ($stops->isEmpty()) {
            return [];
        }

        // Build corridor anchor points: stops with lat/lng from their work_order
        $anchors = $stops->filter(fn($s) =>
            $s->workOrder &&
            $s->workOrder->latitude !== null &&
            $s->workOrder->longitude !== null
        )->map(fn($s) => [
            'lat' => (float)$s->workOrder->latitude,
            'lng' => (float)$s->workOrder->longitude,
        ])->values()->toArray();

        if (empty($anchors)) {
            return [];
        }

        // Get all pings for this colaborador on this route's date
        $attendanceIds = \Illuminate\Support\Facades\DB::table('talento_attendances')
            ->where('colaborador_id', $route->colaborador_id)
            ->whereDate('check_in_at', $route->date)
            ->pluck('id');

        if ($attendanceIds->isEmpty()) {
            return [];
        }

        $pings = TalentoLocationPing::whereIn('attendance_id', $attendanceIds)
            ->orderBy('recorded_at')
            ->get();

        return $this->detectDeviations($route, $pings, $anchors);
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function detectDeviations(TalentoRoute $route, Collection $pings, array $anchors): array
    {
        $created   = [];
        $offStart  = null;   // timestamp when off-corridor streak started
        $offPings  = [];     // pings in current off-corridor streak

        foreach ($pings as $ping) {
            $lat = (float)$ping->latitude;
            $lng = (float)$ping->longitude;

            $nearestDist = $this->distanceToNearestAnchor($lat, $lng, $anchors);
            $isOff       = $nearestDist > self::CORRIDOR_M;

            if ($isOff) {
                if ($offStart === null) {
                    $offStart = Carbon::parse($ping->recorded_at);
                    $offPings = [$ping];
                } else {
                    $offPings[] = $ping;
                }
            } else {
                // Returned to corridor — evaluate streak
                if ($offStart !== null && count($offPings) > 0) {
                    $sustainedMin = (int) $offStart->diffInMinutes(Carbon::parse(end($offPings)->recorded_at));
                    if ($sustainedMin >= self::MIN_SUSTAINED) {
                        // Worst-case ping in the streak
                        $maxDist = max(array_map(fn($p) =>
                            $this->distanceToNearestAnchor((float)$p->latitude, (float)$p->longitude, $anchors),
                            $offPings
                        ));
                        $midPing = $offPings[(int)(count($offPings) / 2)];
                        $created[] = TalentoRouteDeviation::create([
                            'route_id'            => $route->id,
                            'colaborador_id'       => $route->colaborador_id,
                            'detected_lat'         => (float)$midPing->latitude,
                            'detected_lng'         => (float)$midPing->longitude,
                            'deviation_m'          => round($maxDist, 1),
                            'sustained_minutes'    => $sustainedMin,
                            'supervisor_notified'  => false,
                        ]);
                    }
                }
                $offStart = null;
                $offPings = [];
            }
        }

        // Handle streak that reached end of pings
        if ($offStart !== null && count($offPings) > 0) {
            $sustainedMin = (int) $offStart->diffInMinutes(Carbon::parse(end($offPings)->recorded_at));
            if ($sustainedMin >= self::MIN_SUSTAINED) {
                $maxDist = max(array_map(fn($p) =>
                    $this->distanceToNearestAnchor((float)$p->latitude, (float)$p->longitude, $anchors),
                    $offPings
                ));
                $midPing = $offPings[(int)(count($offPings) / 2)];
                $created[] = TalentoRouteDeviation::create([
                    'route_id'           => $route->id,
                    'colaborador_id'     => $route->colaborador_id,
                    'detected_lat'       => (float)$midPing->latitude,
                    'detected_lng'       => (float)$midPing->longitude,
                    'deviation_m'        => round($maxDist, 1),
                    'sustained_minutes'  => $sustainedMin,
                    'supervisor_notified'=> false,
                ]);
            }
        }

        return $created;
    }

    private function distanceToNearestAnchor(float $lat, float $lng, array $anchors): float
    {
        $min = PHP_FLOAT_MAX;
        foreach ($anchors as $a) {
            $d = AttendanceService::haversineMeters($lat, $lng, $a['lat'], $a['lng']);
            if ($d < $min) $min = $d;
        }
        return $min;
    }
}
