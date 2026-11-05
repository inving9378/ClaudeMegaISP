<?php

namespace App\Modules\Addons\Flotas\Services;

use App\Modules\Addons\Flotas\Models\FleetMaintenance;
use App\Modules\Addons\Flotas\Models\FleetVehicle;
use Carbon\Carbon;

/**
 * Predicción estadística (sin IA externa) del próximo servicio por tipo de mantenimiento:
 * intervalo medio en días y km entre servicios consecutivos del mismo tipo.
 */
class ServicePredictionService
{
    public function predict(FleetVehicle $vehicle): array
    {
        $rows = FleetMaintenance::where('vehicle_id', $vehicle->id)
            ->where(function ($q) { $q->whereNull('is_draft')->orWhere('is_draft', false); })
            ->orderBy('service_date')
            ->get(['type', 'service_date', 'service_km'])
            ->groupBy('type');

        $currentKm = (int) $vehicle->current_km;
        $out = [];

        foreach ($rows as $type => $list) {
            if ($list->count() < 2) {
                continue;
            }
            $daysGaps = [];
            $kmGaps = [];
            for ($i = 1; $i < $list->count(); $i++) {
                $prev = $list[$i - 1];
                $cur = $list[$i];
                $d = $prev->service_date->diffInDays($cur->service_date);
                if ($d > 0) {
                    $daysGaps[] = $d;
                }
                if ($prev->service_km !== null && $cur->service_km !== null && $cur->service_km > $prev->service_km) {
                    $kmGaps[] = $cur->service_km - $prev->service_km;
                }
            }

            $last = $list->last();
            $avgDays = $daysGaps ? (int) round(array_sum($daysGaps) / count($daysGaps)) : null;
            $avgKm = $kmGaps ? (int) round(array_sum($kmGaps) / count($kmGaps)) : null;
            $nextDate = $avgDays ? $last->service_date->copy()->addDays($avgDays) : null;
            $nextKm = ($avgKm && $last->service_km !== null) ? $last->service_km + $avgKm : null;

            $out[] = [
                'type'            => $type,
                'samples'         => $list->count(),
                'last_date'       => $last->service_date->toDateString(),
                'last_km'         => $last->service_km,
                'avg_interval_days' => $avgDays,
                'avg_interval_km' => $avgKm,
                'next_date'       => $nextDate?->toDateString(),
                'next_km'         => $nextKm,
                'days_remaining'  => $nextDate ? (int) Carbon::today()->diffInDays($nextDate, false) : null,
                'km_remaining'    => $nextKm !== null ? $nextKm - $currentKm : null,
                'confidence'      => $list->count() >= 4 ? 'alta' : ($list->count() === 3 ? 'media' : 'baja'),
            ];
        }

        return $out;
    }
}
