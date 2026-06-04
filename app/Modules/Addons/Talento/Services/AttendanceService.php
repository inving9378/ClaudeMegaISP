<?php

namespace App\Modules\Addons\Talento\Services;

use App\Modules\Addons\Talento\Models\TalentoAttendance;
use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoLocationPing;
use App\Modules\Addons\Talento\Models\TalentoWorkOrder;
use App\Modules\Addons\Talento\Models\TalentoWorkSite;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    /** Default radius when neither site nor order provides one */
    private const DEFAULT_RADIUS_M = 300;

    /**
     * Haversine distance in meters between two lat/lng points.
     */
    public static function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R = 6371000;
        $phi1 = deg2rad($lat1);
        $phi2 = deg2rad($lat2);
        $dphi = deg2rad($lat2 - $lat1);
        $dlam = deg2rad($lng2 - $lng1);
        $a = sin($dphi / 2) ** 2 + cos($phi1) * cos($phi2) * sin($dlam / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Perform check-in for a collaborator.
     *
     * Rules:
     * - One open attendance at a time (returns existing if already open).
     * - Validates proximity to nearest active work site OR to the assigned work-order location.
     * - If outside radius OR accuracy_m > 100: allows check-in but marks flagged=true.
     * - expected_end_at computed from shift_end (today) or NULL if shift not configured.
     */
    public function checkIn(
        int    $colaboradorId,
        float  $lat,
        float  $lng,
        ?float $accuracyM      = null,
        ?int   $workOrderId    = null
    ): array {
        $colaborador = TalentoColaborador::findOrFail($colaboradorId);

        // Block if already has open attendance
        $existing = TalentoAttendance::where('colaborador_id', $colaboradorId)->open()->latest('check_in_at')->first();
        if ($existing) {
            return ['status' => 'already_open', 'attendance' => $existing];
        }

        $defaultRadius = (int) DB::table('settings')
            ->where('key', 'talento_checkin_default_radius_m')
            ->value('value') ?? self::DEFAULT_RADIUS_M;

        $withinGeofence = false;
        $flagged        = false;
        $flagReason     = null;
        $matchedSiteId  = null;

        // Check accuracy flag
        if ($accuracyM !== null && $accuracyM > 100) {
            $flagged    = true;
            $flagReason = "GPS impreciso ({$accuracyM}m)";
        }

        // Try to match a work site
        $sites = TalentoWorkSite::active()->get();
        foreach ($sites as $site) {
            $dist = self::haversineMeters($lat, $lng, (float)$site->latitude, (float)$site->longitude);
            $radius = $site->radius_m ?: $defaultRadius;
            if ($dist <= $radius) {
                $withinGeofence = true;
                $matchedSiteId  = $site->id;
                break;
            }
        }

        // If not matched to site, try work-order location (future: lat/lng on work order)
        if (!$withinGeofence && $workOrderId) {
            $wo = TalentoWorkOrder::find($workOrderId);
            if ($wo && $wo->latitude && $wo->longitude) {
                $dist = self::haversineMeters($lat, $lng, (float)$wo->latitude, (float)$wo->longitude);
                if ($dist <= $defaultRadius) {
                    $withinGeofence = true;
                }
            }
        }

        if (!$withinGeofence && !$flagged) {
            $flagged    = true;
            $flagReason = "Fuera de rango de ubicaciones válidas";
        }

        // Compute expected_end_at from shift
        $expectedEnd = null;
        if ($colaborador->shift_end) {
            $expectedEnd = Carbon::today()->setTimeFromTimeString($colaborador->shift_end);
        }

        $attendance = TalentoAttendance::create([
            'colaborador_id'           => $colaboradorId,
            'check_in_at'              => now(),
            'check_in_lat'             => $lat,
            'check_in_lng'             => $lng,
            'check_in_site_id'         => $matchedSiteId,
            'check_in_work_order_id'   => $workOrderId,
            'check_in_within_geofence' => $withinGeofence,
            'check_in_flagged'         => $flagged,
            'check_in_flag_reason'     => $flagReason,
            'expected_end_at'          => $expectedEnd,
            'status'                   => $flagged ? 'flagged' : 'open',
            'day_type'                 => 'worked',
        ]);

        return [
            'status'     => $flagged ? 'flagged' : 'ok',
            'attendance' => $attendance->load('site'),
            'distance_m' => null,
        ];
    }

    /**
     * Perform check-out for the open attendance of a collaborator.
     */
    public function checkOut(int $colaboradorId, ?float $lat = null, ?float $lng = null): array
    {
        $attendance = TalentoAttendance::where('colaborador_id', $colaboradorId)
            ->open()
            ->latest('check_in_at')
            ->first();

        if (!$attendance) {
            return ['status' => 'no_open_attendance'];
        }

        $attendance->update([
            'check_out_at'  => now(),
            'check_out_lat' => $lat,
            'check_out_lng' => $lng,
            'status'        => 'closed',
        ]);

        return ['status' => 'ok', 'attendance' => $attendance->fresh()];
    }

    /**
     * Store a location ping — only persists if the collaborator has an open attendance.
     */
    public function storePing(
        int    $colaboradorId,
        float  $lat,
        float  $lng,
        ?float $accuracyM = null,
        ?int   $battery   = null,
        ?string $recordedAt = null
    ): ?TalentoLocationPing {
        $attendance = TalentoAttendance::where('colaborador_id', $colaboradorId)
            ->open()
            ->latest('check_in_at')
            ->first();

        if (!$attendance) {
            return null; // silently drop ping — no open attendance
        }

        return TalentoLocationPing::create([
            'colaborador_id' => $colaboradorId,
            'attendance_id'  => $attendance->id,
            'latitude'       => $lat,
            'longitude'      => $lng,
            'accuracy_m'     => $accuracyM,
            'battery'        => $battery,
            'recorded_at'    => $recordedAt ? Carbon::parse($recordedAt) : now(),
        ]);
    }
}
