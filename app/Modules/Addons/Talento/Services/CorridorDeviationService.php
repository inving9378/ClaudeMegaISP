<?php

namespace App\Modules\Addons\Talento\Services;

use App\Modules\Addons\Talento\Models\TalentoLocationPing;
use App\Modules\Addons\Talento\Models\TalentoProject;
use App\Modules\Addons\Talento\Models\TalentoProjectDeviation;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CorridorDeviationService
{
    /** Corredor medio-ancho en metros — absorbe error GPS y desvíos menores */
    private const CORRIDOR_M    = 300;

    /** Minutos consecutivos fuera del corredor antes de alertar */
    private const MIN_SUSTAINED = 8;

    /**
     * Analiza los pings de los colaboradores del proyecto en un rango de fechas
     * contra el corridor_path del proyecto.
     * Retorna las nuevas TalentoProjectDeviation creadas.
     */
    public function analyze(TalentoProject $project, Carbon $from, Carbon $to): array
    {
        if (empty($project->corridor_path) || count($project->corridor_path) < 2) {
            return [];
        }

        $anchors = array_map(
            fn($pt) => ['lat' => (float)$pt[0], 'lng' => (float)$pt[1]],
            $project->corridor_path
        );

        // Colaboradores participantes en el proyecto (via activity_report_participants)
        $colaboradorIds = DB::table('talento_activity_report_participants as p')
            ->join('talento_project_activity_reports as r', 'r.id', '=', 'p.report_id')
            ->join('talento_project_activities as a', 'a.id', '=', 'r.activity_id')
            ->where('a.project_id', $project->id)
            ->distinct()
            ->pluck('p.colaborador_id');

        if ($colaboradorIds->isEmpty()) {
            return [];
        }

        // Pings de esos colaboradores en el rango, via attendances
        $attendanceIds = DB::table('talento_attendances')
            ->whereIn('colaborador_id', $colaboradorIds)
            ->whereBetween('check_in_at', [$from, $to])
            ->pluck('id');

        if ($attendanceIds->isEmpty()) {
            return [];
        }

        $pings = TalentoLocationPing::whereIn('attendance_id', $attendanceIds)
            ->orderBy('recorded_at')
            ->get()
            ->groupBy(fn($p) => $p->attendance->colaborador_id ?? 0);

        $created = [];
        foreach ($pings as $colaboradorId => $colaboradorPings) {
            $deviations = $this->detectDeviations(
                $project->id,
                (int)$colaboradorId,
                collect($colaboradorPings),
                $anchors
            );
            $created = array_merge($created, $deviations);
        }

        return $created;
    }

    // ── Private ──────────────────────────────────────────────────────────────

    private function detectDeviations(
        int $projectId,
        int $colaboradorId,
        Collection $pings,
        array $anchors
    ): array {
        $created  = [];
        $offStart = null;
        $offPings = [];

        foreach ($pings as $ping) {
            $lat  = (float)$ping->latitude;
            $lng  = (float)$ping->longitude;
            $dist = $this->distanceToNearestAnchor($lat, $lng, $anchors);
            $isOff = $dist > self::CORRIDOR_M;

            if ($isOff) {
                if ($offStart === null) {
                    $offStart = Carbon::parse($ping->recorded_at);
                }
                $offPings[] = $ping;
            } else {
                if ($offStart !== null && count($offPings) > 0) {
                    $sustainedMin = (int)$offStart->diffInMinutes(
                        Carbon::parse(end($offPings)->recorded_at)
                    );
                    if ($sustainedMin >= self::MIN_SUSTAINED) {
                        $created[] = $this->createDeviation($projectId, $colaboradorId, $offPings, $anchors, $offStart, $sustainedMin);
                    }
                }
                $offStart = null;
                $offPings = [];
            }
        }

        // Streak que llega al final del rango
        if ($offStart !== null && count($offPings) > 0) {
            $sustainedMin = (int)$offStart->diffInMinutes(Carbon::parse(end($offPings)->recorded_at));
            if ($sustainedMin >= self::MIN_SUSTAINED) {
                $created[] = $this->createDeviation($projectId, $colaboradorId, $offPings, $anchors, $offStart, $sustainedMin);
            }
        }

        return $created;
    }

    private function createDeviation(
        int $projectId,
        int $colaboradorId,
        array $offPings,
        array $anchors,
        Carbon $offStart,
        int $sustainedMin
    ): TalentoProjectDeviation {
        $maxDist = max(array_map(
            fn($p) => $this->distanceToNearestAnchor((float)$p->latitude, (float)$p->longitude, $anchors),
            $offPings
        ));
        $midPing = $offPings[(int)(count($offPings) / 2)];

        return TalentoProjectDeviation::create([
            'project_id'          => $projectId,
            'colaborador_id'      => $colaboradorId,
            'detected_lat'        => (float)$midPing->latitude,
            'detected_lng'        => (float)$midPing->longitude,
            'deviation_m'         => round($maxDist, 1),
            'sustained_minutes'   => $sustainedMin,
            'detected_at'         => $offStart,
            'supervisor_notified' => false,
            'created_at'          => now(),
        ]);
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
