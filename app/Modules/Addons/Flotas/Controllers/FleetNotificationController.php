<?php

namespace App\Modules\Addons\Flotas\Controllers;

use App\Modules\Addons\Flotas\Models\FleetGeofence;
use App\Modules\Addons\Flotas\Models\FleetGeofenceEvent;
use App\Modules\Addons\Flotas\Models\FleetNotificationLog;
use App\Modules\Addons\Flotas\Models\FleetNotificationPreference;
use App\Modules\Addons\Flotas\Models\FleetVehicle;
use App\Modules\Addons\Flotas\Services\Notifications\FleetNotificationDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// Sub-fase 3.3 — Preferencias de alerta del usuario + log de notificaciones.
class FleetNotificationController extends FleetBaseController
{
    // GET /flotas/api/vehiculos/{id}/notification-preference — preferencia del usuario actual para este vehículo
    public function myPreference(int $id): JsonResponse
    {
        $this->autorizar('fleet.view');

        $vehicle = FleetVehicle::forClient($this->clientId())->findOrFail($id);
        $user = auth()->user();

        // Preferencias del usuario para ESTE vehículo (una fila por geocerca, o una con geofence_id null = todas).
        $prefs = FleetNotificationPreference::where('user_id', $user->id)
            ->where('vehicle_id', $vehicle->id)
            ->get();

        $first = $prefs->first();
        $allGeofences = $prefs->contains(fn ($p) => $p->geofence_id === null);

        $geofences = FleetGeofence::whereHas('vehicles', fn ($q) => $q->where('fleet_vehicles.id', $vehicle->id))
            ->get(['id', 'name']);

        return response()->json([
            'exists'       => $prefs->isNotEmpty(),
            'active'       => $first?->active ?? false,
            'all_geofences'=> $allGeofences || $prefs->isEmpty(),
            'geofence_ids' => $allGeofences ? [] : $prefs->pluck('geofence_id')->filter()->values(),
            'event_types'  => $first?->event_types ?? ['enter', 'exit'],
            'channels'     => $first?->channels ?? ['email'],
            'user_email'   => $user->email,
            'user_phone'   => $user->phone,
            'geofences'    => $geofences,
        ]);
    }

    // POST /flotas/api/vehiculos/{id}/notification-preference — guarda la preferencia del usuario
    public function savePreference(Request $request, int $id): JsonResponse
    {
        $this->autorizar('fleet.view');

        $vehicle = FleetVehicle::forClient($this->clientId())->findOrFail($id);
        $user = auth()->user();

        $data = $request->validate([
            'active'         => 'required|boolean',
            'all_geofences'  => 'sometimes|boolean',
            'geofence_ids'   => 'sometimes|array',
            'geofence_ids.*' => 'integer',
            'event_types'    => 'required|array|min:1',
            'event_types.*'  => 'in:enter,exit',
            'channels'       => 'required|array|min:1',
            'channels.*'     => 'in:email,whatsapp',
        ]);

        // Reemplaza las preferencias del usuario para este vehículo.
        FleetNotificationPreference::where('user_id', $user->id)
            ->where('vehicle_id', $vehicle->id)
            ->delete();

        if ($data['active']) {
            $allGeofences = $data['all_geofences'] ?? empty($data['geofence_ids']);

            // Valida que las geocercas pertenezcan a este vehículo (tenant-safe).
            $validGeofenceIds = $allGeofences ? [] : FleetGeofence::whereHas('vehicles', fn ($q) => $q->where('fleet_vehicles.id', $vehicle->id))
                ->whereIn('id', $data['geofence_ids'] ?? [])
                ->pluck('id')->all();

            $targets = $allGeofences ? [null] : $validGeofenceIds;
            if (empty($targets)) {
                $targets = [null];
            }

            foreach ($targets as $gid) {
                FleetNotificationPreference::create([
                    'user_id'     => $user->id,
                    'vehicle_id'  => $vehicle->id,
                    'geofence_id' => $gid,
                    'event_types' => array_values($data['event_types']),
                    'channels'    => array_values($data['channels']),
                    'active'      => true,
                ]);
            }
        }

        return response()->json(['success' => true]);
    }

    // GET /flotas/api/notificaciones-log — log paginado (admin)
    public function log(Request $request): JsonResponse
    {
        $this->autorizar('fleet.notifications.view');

        $logs = FleetNotificationLog::with(['event.vehicle', 'event.geofence', 'user'])
            ->whereHas('event.vehicle', fn ($v) => $v->forClient($this->clientId()))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderByDesc('id')
            ->paginate(30);

        // En el portal (#664) no se expone a quién se mandó (personal de Meganet: nombre, correo o
        // teléfono) ni el error interno del envío; el cliente ve vehículo, evento, canal, estado y fecha.
        $portal = $this->enPortal();
        $items = collect($logs->items())->map(fn ($l) => [
            'id'            => $l->id,
            'created_at'    => $l->created_at?->toIso8601String(),
            'vehicle'       => $l->event?->vehicle?->display_name ?? '—',
            'vehicle_id'    => $l->event?->vehicle_id,
            'geofence'      => $l->event?->geofence?->name ?? '—',
            'event_type'    => $l->event?->event_type,
            'user'          => $portal ? null : (trim(($l->user?->name ?? '') . ' ' . ($l->user?->father_last_name ?? '')) ?: ($l->user?->login_user ?? ('#' . $l->user_id))),
            'channel'       => $l->channel,
            'destination'   => $portal ? null : $l->destination,
            'status'        => $l->status,
            'error_message' => $portal ? null : $l->error_message,
        ]);

        return response()->json([
            'items' => $items,
            'total' => $logs->total(),
            'page'  => $logs->currentPage(),
            'last_page' => $logs->lastPage(),
        ]);
    }

    // POST /flotas/api/notificaciones-log/{id}/resend — reintenta el evento de un log fallido
    public function resend(int $id, FleetNotificationDispatcher $dispatcher): JsonResponse
    {
        $this->autorizar('fleet.notifications.manage');

        $log = FleetNotificationLog::with('event.vehicle')->findOrFail($id);
        // Tenant check
        $vehicle = $log->event?->vehicle;
        if (!$vehicle || !FleetVehicle::forClient($this->clientId())->whereKey($vehicle->id)->exists()) {
            abort(404);
        }

        $event = FleetGeofenceEvent::with(['vehicle', 'geofence'])->find($log->event_id);
        if (!$event) {
            return response()->json(['success' => false, 'message' => 'Evento ya no existe.'], 404);
        }

        $results = $dispatcher->dispatch($event);
        return response()->json(['success' => true, 'results' => $results]);
    }

}
