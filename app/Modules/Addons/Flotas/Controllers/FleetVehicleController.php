<?php

namespace App\Modules\Addons\Flotas\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Flotas\Models\FleetDocument;
use App\Modules\Addons\Flotas\Models\FleetMaintenance;
use App\Modules\Addons\Flotas\Models\FleetVehicle;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FleetVehicleController extends Controller
{
    // GET /flotas/api/vehiculos/data/operadores — lista buscable de operadores (no clientes)
    public function operators(Request $request): JsonResponse
    {
        $this->authorize('fleet.view');

        $search = trim((string) $request->input('search', ''));

        $users = User::query()
            ->whereDoesntHave('roles', fn($q) => $q->where('name', 'client'))
            ->has('roles')
            ->when($search !== '', fn($q) => $q->where(fn($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('father_last_name', 'like', "%{$search}%")
                ->orWhere('mother_last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'name', 'father_last_name', 'mother_last_name', 'email'])
            ->map(fn($u) => [
                'id'    => $u->id,
                'name'  => trim("{$u->name} {$u->father_last_name} {$u->mother_last_name}"),
                'email' => $u->email,
            ]);

        return response()->json(['users' => $users]);
    }

    // GET /flotas/api/vehiculos
    public function index(Request $request): JsonResponse
    {
        $this->authorize('fleet.view');

        $q = FleetVehicle::with(['currentAssignment.operator'])
            ->forClient($this->clientId())
            ->orderByRaw("FIELD(status,'active','in_workshop','inactive')")
            ->orderBy('brand');

        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }

        $vehicles = $q->get()->map(fn($v) => array_merge($v->toArray(), [
            'display_name'              => $v->display_name,
            'next_service_days'         => $v->next_service_days,
            'expiring_documents_count'  => $v->expiring_documents_count,
            'expired_documents_count'   => $v->expired_documents_count,
        ]));

        return response()->json(['vehicles' => $vehicles]);
    }

    // POST /flotas/api/vehiculos
    public function store(Request $request): JsonResponse
    {
        $this->authorize('fleet.manage');

        $data = $request->validate([
            'plates'               => 'nullable|string|max:20',
            'brand'                => 'required|string|max:100',
            'model'                => 'required|string|max:100',
            'year'                 => 'nullable|integer|min:1900|max:2100',
            'color'                => 'nullable|string|max:50',
            'vin'                  => 'nullable|string|max:50',
            'motor_number'         => 'nullable|string|max:50',
            'vehicle_type'         => 'nullable|in:car,pickup,truck,motorcycle,other',
            'fuel_type'            => 'nullable|in:gasoline,diesel,electric,hybrid',
            'tank_capacity_liters' => 'nullable|numeric|min:0',
            'current_km'           => 'nullable|integer|min:0',
            'status'               => 'nullable|in:active,in_workshop,inactive',
            'habitual_location'    => 'nullable|string|max:255',
            'notes'                => 'nullable|string',
            'has_gps'              => 'boolean',
            'gps_brand'            => 'nullable|string|max:100',
            'gps_model'            => 'nullable|string|max:100',
            'gps_imei'             => 'nullable|string|max:20',
            'gps_sim'              => 'nullable|string|max:20',
            'gps_carrier'          => 'nullable|string|max:50',
        ]);

        $data['client_id'] = $this->clientId();
        $vehicle = FleetVehicle::create($data);

        return response()->json(['vehicle' => $vehicle], 201);
    }

    // GET /flotas/api/vehiculos/{id}
    public function show(int $id): JsonResponse
    {
        $this->authorize('fleet.view');

        $vehicle = FleetVehicle::with([
            'currentAssignment.operator',
            'maintenances.provider',
            'maintenances.files',
            'documents',
            'fuelLog',
            'photos',
        ])->forClient($this->clientId())->findOrFail($id);

        return response()->json(['vehicle' => $vehicle]);
    }

    // PATCH /flotas/api/vehiculos/{id}
    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorize('fleet.manage');

        $vehicle = FleetVehicle::forClient($this->clientId())->findOrFail($id);
        $vehicle->update($request->except(['client_id']));

        return response()->json(['vehicle' => $vehicle->fresh()]);
    }

    // DELETE /flotas/api/vehiculos/{id}
    public function destroy(int $id): JsonResponse
    {
        $this->authorize('fleet.manage');

        FleetVehicle::forClient($this->clientId())->findOrFail($id)->delete();

        return response()->json(['ok' => true]);
    }

    // POST /flotas/api/vehiculos/{id}/restore
    public function restore(int $id): JsonResponse
    {
        $this->authorize('fleet.manage');

        FleetVehicle::withTrashed()->forClient($this->clientId())->findOrFail($id)->restore();

        return response()->json(['ok' => true]);
    }

    // GET /flotas/api/vehiculos/data/dashboard
    public function dashboard(): JsonResponse
    {
        $this->authorize('fleet.view');

        $clientId = $this->clientId();
        $baseQ = fn() => FleetVehicle::forClient($clientId);

        $total     = $baseQ()->count();
        $active    = $baseQ()->where('status', 'active')->count();
        $workshop  = $baseQ()->where('status', 'in_workshop')->count();

        $expiredDocs = FleetDocument::whereHas('vehicle', fn($q) => $q->forClient($clientId))
            ->whereNotNull('expiration_date')
            ->where('expiration_date', '<', now())
            ->count();

        $expiringDocs = FleetDocument::whereHas('vehicle', fn($q) => $q->forClient($clientId))
            ->whereNotNull('expiration_date')
            ->whereBetween('expiration_date', [now(), now()->addDays(30)])
            ->count();

        $gastoMes = \DB::table('fleet_maintenances')
            ->join('fleet_vehicles', 'fleet_vehicles.id', '=', 'fleet_maintenances.vehicle_id')
            ->where(fn($q) => $clientId ? $q->where('fleet_vehicles.client_id', $clientId) : $q->whereNull('fleet_vehicles.client_id'))
            ->whereMonth('fleet_maintenances.service_date', now()->month)
            ->whereYear('fleet_maintenances.service_date', now()->year)
            ->whereNull('fleet_maintenances.deleted_at')
            ->sum('fleet_maintenances.total_cost');

        return response()->json(compact('total', 'active', 'workshop', 'expiredDocs', 'expiringDocs', 'gastoMes'));
    }

    // GET /flotas/api/vehiculos/data/alertas
    public function alertas(): JsonResponse
    {
        $this->authorize('fleet.view');

        $clientId = $this->clientId();

        $docs = FleetDocument::with('vehicle')
            ->whereHas('vehicle', fn($q) => $q->forClient($clientId))
            ->whereNotNull('expiration_date')
            ->where('expiration_date', '<=', now()->addDays(30))
            ->orderBy('expiration_date')
            ->get()
            ->map(fn($d) => [
                'type'        => 'document',
                'vehicle_id'  => $d->vehicle_id,
                'vehicle'     => $d->vehicle?->display_name,
                'doc_type'    => $d->document_type,
                'expiration'  => $d->expiration_date,
                'days'        => $d->days_until_expiration,
                'status'      => $d->status,
            ]);

        $maintenances = FleetMaintenance::with('vehicle')
            ->whereHas('vehicle', fn($q) => $q->forClient($clientId))
            ->whereNotNull('next_service_date')
            ->where('next_service_date', '<=', now()->addDays(30))
            ->orderBy('next_service_date')
            ->get()
            ->map(fn($m) => [
                'type'        => 'maintenance',
                'vehicle_id'  => $m->vehicle_id,
                'vehicle'     => $m->vehicle?->display_name,
                'days'        => now()->diffInDays($m->next_service_date, false),
            ]);

        return response()->json(['alertas' => $docs->merge($maintenances)->sortBy('days')->values()]);
    }

    private function clientId(): ?int
    {
        $user = auth()->user();
        if ($user->hasRole(['super-administrator', 'DESARROLLADOR'])) {
            return null; // ve flota interna Meganet
        }
        return $user->client_id ?? null;
    }
}
