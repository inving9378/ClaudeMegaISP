<?php

namespace App\Modules\Addons\Flotas\Services;

use App\Modules\Addons\Flotas\Jobs\SendGeofenceNotificationsJob;
use App\Modules\Addons\Flotas\Models\FleetGeofence;
use App\Modules\Addons\Flotas\Models\FleetGeofenceEvent;
use App\Modules\Addons\Flotas\Models\FleetPosition;
use App\Modules\Addons\Flotas\Services\Geometry\PointInPolygon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Sub-fase 3.2 — Detección automática de entradas/salidas de geocercas.
 *
 * Compara cada posición con la inmediatamente anterior del vehículo:
 *  - fuera → dentro  = evento "enter"
 *  - dentro → fuera  = evento "exit"
 * Respeta el tipo de la geocerca (enter / exit / both) y la multi-tenancy
 * (una geocerca solo se asigna a vehículos de su mismo tenant vía pivot).
 */
class GeofenceDetectionService
{
    /** Cache de geocercas (con bbox precalculado) por vehículo, vivo durante el batch. */
    private array $geofenceCache = [];

    /**
     * Procesa UNA posición (tiempo real / inserción individual).
     * Delega en processBatch para reusar la misma lógica.
     *
     * @return array eventos creados
     */
    public function process(FleetPosition $newPosition): array
    {
        return $this->processBatch([$newPosition]);
    }

    /**
     * Procesa un batch de posiciones eficientemente:
     *  - agrupa por vehículo
     *  - carga las geocercas UNA sola vez por vehículo (cache + bbox)
     *  - recorre cronológicamente manteniendo el estado dentro/fuera en memoria
     *    (sin una query "posición anterior" por cada ping)
     *
     * @param FleetPosition[]|Collection $positions
     * @return array eventos creados
     */
    public function processBatch($positions): array
    {
        $positions = collect($positions)->filter()->values();
        if ($positions->isEmpty()) {
            return [];
        }

        $allEvents = [];

        foreach ($positions->groupBy('vehicle_id') as $vehicleId => $list) {
            $vehicleId = (int) $vehicleId;
            $geofences = $this->geofencesFor($vehicleId);
            if ($geofences->isEmpty()) {
                continue;
            }

            // Orden cronológico (con desempate por id).
            $ordered = $list->sort(function ($a, $b) {
                return ($a->recorded_at <=> $b->recorded_at) ?: ($a->id <=> $b->id);
            })->values();

            // Semilla del estado dentro/fuera con la posición previa al batch (si existe).
            $seed = $this->previousPosition($vehicleId, $ordered->first());
            $events = $this->walk($vehicleId, $ordered, $geofences, $seed);
            $allEvents = array_merge($allEvents, $events);
        }

        return $allEvents;
    }

    /**
     * Recorre las posiciones en orden, detecta transiciones y persiste los eventos.
     */
    private function walk(int $vehicleId, Collection $ordered, Collection $geofences, ?FleetPosition $seed): array
    {
        // Estado por geocerca: true=dentro, false=fuera, null=desconocido (sin posición previa).
        $state = [];
        foreach ($geofences as $g) {
            $state[$g->id] = $seed ? $this->insideOf($seed, $g) : null;
        }

        $rows = [];
        $now = now();

        foreach ($ordered as $pos) {
            foreach ($geofences as $g) {
                $inside = $this->insideOf($pos, $g);
                $was = $state[$g->id];

                // Sin estado previo (primera posición jamás registrada) → solo fija estado.
                if ($was !== null) {
                    if (!$was && $inside && in_array($g->type, ['enter', 'both'], true)) {
                        $rows[] = $this->row($vehicleId, $g->id, 'enter', $pos, $now);
                    } elseif ($was && !$inside && in_array($g->type, ['exit', 'both'], true)) {
                        $rows[] = $this->row($vehicleId, $g->id, 'exit', $pos, $now);
                    }
                }

                $state[$g->id] = $inside;
            }
        }

        if (!empty($rows)) {
            $beforeId = (int) (FleetGeofenceEvent::max('id') ?? 0);
            FleetGeofenceEvent::insert($rows);

            // Sub-fase 3.3: encolar notificaciones de los eventos recién creados.
            // Async (queue) para no bloquear la detección; try/catch para que un fallo
            // al encolar NUNCA tumbe la detección/ingestión (los eventos ya quedaron en BD).
            try {
                $newIds = FleetGeofenceEvent::where('id', '>', $beforeId)
                    ->where('vehicle_id', $vehicleId)
                    ->pluck('id')->all();
                if (!empty($newIds)) {
                    SendGeofenceNotificationsJob::dispatch($newIds);
                }
            } catch (\Throwable $e) {
                Log::warning('Flotas: no se pudo encolar notificaciones de geocerca: ' . $e->getMessage());
            }
        }

        return $rows;
    }

    private function row(int $vehicleId, int $geofenceId, string $type, FleetPosition $pos, $now): array
    {
        return [
            'vehicle_id'  => $vehicleId,
            'geofence_id' => $geofenceId,
            'event_type'  => $type,
            'position_id' => $pos->id,
            'occurred_at' => $pos->recorded_at,
            'created_at'  => $now,
        ];
    }

    /** ¿La posición está dentro de la geocerca? bbox primero (barato), luego ray casting. */
    private function insideOf(FleetPosition $pos, object $g): bool
    {
        $point = [(float) $pos->lat, (float) $pos->lng];
        if (!PointInPolygon::inBoundingBox($point, $g->bbox)) {
            return false;
        }
        return PointInPolygon::contains($point, $g->polygon);
    }

    /**
     * Geocercas activas asignadas al vehículo, con polígono normalizado y bbox precalculado.
     * Cacheadas por vehículo para todo el batch.
     *
     * @return Collection<object{id:int,type:string,polygon:array,bbox:array}>
     */
    private function geofencesFor(int $vehicleId): Collection
    {
        if (isset($this->geofenceCache[$vehicleId])) {
            return $this->geofenceCache[$vehicleId];
        }

        $geofences = FleetGeofence::active()
            ->whereHas('vehicles', fn($q) => $q->where('fleet_vehicles.id', $vehicleId))
            ->get(['id', 'type', 'polygon'])
            ->map(function (FleetGeofence $g) {
                $polygon = collect($g->polygon ?? [])
                    ->map(fn($p) => [(float) ($p[0] ?? 0), (float) ($p[1] ?? 0)])
                    ->all();

                return (object) [
                    'id'      => $g->id,
                    'type'    => $g->type,
                    'polygon' => $polygon,
                    'bbox'    => PointInPolygon::boundingBox($polygon),
                ];
            })
            // Descarta geocercas con polígono inválido (<3 puntos → bbox null).
            ->filter(fn($g) => $g->bbox !== null)
            ->values();

        return $this->geofenceCache[$vehicleId] = $geofences;
    }

    /** Última posición del vehículo anterior a $p (para semilla del estado). */
    private function previousPosition(int $vehicleId, FleetPosition $p): ?FleetPosition
    {
        return FleetPosition::where('vehicle_id', $vehicleId)
            ->where(function ($q) use ($p) {
                $q->where('recorded_at', '<', $p->recorded_at)
                  ->orWhere(function ($q2) use ($p) {
                      $q2->where('recorded_at', $p->recorded_at)->where('id', '<', $p->id);
                  });
            })
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->first();
    }

    /** Limpia el cache de geocercas (útil entre reprocesos si cambian las geocercas). */
    public function clearCache(): void
    {
        $this->geofenceCache = [];
    }
}
