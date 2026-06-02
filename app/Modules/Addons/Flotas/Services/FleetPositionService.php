<?php

namespace App\Modules\Addons\Flotas\Services;

use App\Modules\Addons\Flotas\Models\FleetDevice;
use App\Modules\Addons\Flotas\Models\FleetPosition;
use App\Modules\Addons\Flotas\Models\FleetVehicle;
use App\Modules\Addons\Flotas\Services\Gps\Position;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FleetPositionService
{
    /**
     * Guarda múltiples posiciones de forma eficiente (insert por lotes) y
     * actualiza la cabecera del dispositivo (last_seen_at, total_pings, status).
     *
     * @param Position[] $positions
     */
    public function saveBatch(array $positions, int $vehicleId, int $deviceId): int
    {
        if (empty($positions)) {
            return 0;
        }

        $rows = array_map(fn(Position $p) => $p->toAttributes($vehicleId, $deviceId), $positions);

        // Insert en chunks para no exceder límites de placeholders/paquete.
        foreach (array_chunk($rows, 500) as $chunk) {
            FleetPosition::insert($chunk);
        }

        $last = end($rows);
        FleetDevice::where('id', $deviceId)->update([
            'last_seen_at' => $last['recorded_at'],
            'total_pings'  => DB::raw('total_pings + ' . count($rows)),
            'status'       => 'active',
        ]);

        return count($rows);
    }

    public function getLastPosition(int $vehicleId): ?FleetPosition
    {
        return FleetPosition::where('vehicle_id', $vehicleId)
            ->orderByDesc('recorded_at')
            ->first();
    }

    /**
     * Historial entre fechas (orden cronológico ascendente para dibujar la ruta).
     */
    public function getHistory(int $vehicleId, $from, $to): Collection
    {
        return FleetPosition::where('vehicle_id', $vehicleId)
            ->whereBetween('recorded_at', [Carbon::parse($from), Carbon::parse($to)])
            ->orderBy('recorded_at')
            ->get();
    }

    /**
     * Última posición de cada vehículo con GPS activo (para el mapa global).
     * Respeta multi-tenancy filtrando por client_id.
     */
    public function getCurrentPositions(?int $clientId): Collection
    {
        $vehicles = FleetVehicle::forClient($clientId)
            ->where('has_gps', true)
            ->with(['device', 'lastPosition', 'currentAssignment.operator'])
            ->get();

        return $vehicles->map(function (FleetVehicle $v) {
            $pos = $v->lastPosition;

            return [
                'vehicle_id'   => $v->id,
                'display_name' => $v->display_name,
                'plates'       => $v->plates,
                'brand'        => $v->brand,
                'model'        => $v->model,
                'status'       => $v->status,
                'operator'     => $v->currentAssignment?->operator?->name,
                'device'       => $v->device ? [
                    'imei'   => $v->device->imei,
                    'brand'  => $v->device->brand,
                    'status' => $v->device->status,
                ] : null,
                'position'     => $pos ? [
                    'lat'         => (float) $pos->lat,
                    'lng'         => (float) $pos->lng,
                    'speed'       => (float) $pos->speed,
                    'heading'     => (float) $pos->heading,
                    'recorded_at' => $pos->recorded_at?->toIso8601String(),
                ] : null,
                'live_status'  => $this->liveStatus($pos),
            ];
        });
    }

    /**
     * Clasificación visual para el mapa:
     *  moving  (verde)  — speed > 3 km/h y ping < 1h
     *  stopped (amarillo)— detenido y ping < 1h
     *  idle    (gris)    — último ping entre 1h y 6h
     *  offline (rojo)    — sin señal > 6h o sin posición
     */
    public function liveStatus(?FleetPosition $pos): string
    {
        if (!$pos || !$pos->recorded_at) {
            return 'offline';
        }

        $minutes = $pos->recorded_at->diffInMinutes(Carbon::now());

        if ($minutes > 360) {
            return 'offline';
        }
        if ($minutes > 60) {
            return 'idle';
        }

        return $pos->speed > 3 ? 'moving' : 'stopped';
    }
}
