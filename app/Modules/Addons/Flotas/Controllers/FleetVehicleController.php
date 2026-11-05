<?php

namespace App\Modules\Addons\Flotas\Controllers;

use App\Modules\Addons\Flotas\Models\FleetDocument;
use App\Modules\Addons\Flotas\Models\FleetFuelLog;
use App\Modules\Addons\Flotas\Models\FleetMaintenance;
use App\Modules\Addons\Flotas\Models\FleetVehicle;
use App\Modules\Addons\Flotas\Support\FleetVehicleRules;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FleetVehicleController extends FleetBaseController
{
    public function operators(Request $request): JsonResponse
    {
        $this->autorizar('fleet.view');

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

    public function index(Request $request): JsonResponse
    {
        $this->autorizar('fleet.view');

        $q = FleetVehicle::with(['currentAssignment.operator'])
            ->forClient($this->clientId())
            ->orderByRaw("FIELD(status,'active','in_workshop','inactive')")
            ->orderBy('brand');

        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }

        $vehicles = $q->get()->map(fn($v) => array_merge($v->toArray(), [
            'display_name'             => $v->display_name,
            'next_service_days'        => $v->next_service_days,
            'expiring_documents_count' => $v->expiring_documents_count,
            'expired_documents_count'  => $v->expired_documents_count,
        ]));

        return response()->json(['vehicles' => $vehicles]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->autorizar('fleet.manage');

        $data = $request->validate(FleetVehicleRules::store());

        $data['client_id'] = $this->clientId();
        $vehicle = FleetVehicle::create($data);

        return response()->json(['vehicle' => $vehicle], 201);
    }

    public function show(int $id): JsonResponse
    {
        $this->autorizar('fleet.view');

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

    public function update(Request $request, int $id): JsonResponse
    {
        $this->autorizar('fleet.manage');

        $vehicle = $this->vehicleForClient($id);
        $vehicle->update($request->except(['client_id']));

        return response()->json(['vehicle' => $vehicle->fresh()]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->autorizar('fleet.manage');

        $this->vehicleForClient($id)->delete();

        return response()->json(['ok' => true]);
    }

    public function restore(int $id): JsonResponse
    {
        $this->autorizar('fleet.manage');

        FleetVehicle::withTrashed()->forClient($this->clientId())->findOrFail($id)->restore();

        return response()->json(['ok' => true]);
    }

    public function dashboard(): JsonResponse
    {
        $this->autorizar('fleet.view');

        $clientId = $this->clientId();
        $baseQ = fn() => FleetVehicle::forClient($clientId);

        $total    = $baseQ()->count();
        $active   = $baseQ()->where('status', 'active')->count();
        $workshop = $baseQ()->where('status', 'in_workshop')->count();

        $expiredDocs = FleetDocument::forClient($clientId)
            ->whereNotNull('expiration_date')
            ->where('expiration_date', '<', now())
            ->count();

        $expiringDocs = FleetDocument::forClient($clientId)
            ->whereNotNull('expiration_date')
            ->whereBetween('expiration_date', [now(), now()->addDays(30)])
            ->count();

        $gastoMes = FleetMaintenance::forClient($clientId)
            ->whereMonth('service_date', now()->month)
            ->whereYear('service_date', now()->year)
            ->sum('total_cost');

        return response()->json(compact('total', 'active', 'workshop', 'expiredDocs', 'expiringDocs', 'gastoMes'));
    }

    /**
     * Ranking de costo por km (mantenimiento + combustible) entre vehículos, con atípicos (item #612).
     * Estadística simple (cerca de Tukey sobre costo/km), sin IA. km = odómetro máx − mín visto en el periodo.
     */
    public function costoKm(Request $request): JsonResponse
    {
        $this->autorizar('fleet.view');

        $months = max(1, min(36, (int) $request->input('months', 12)));
        $from   = now()->subMonths($months)->startOfDay();
        $clientId = $this->clientId();

        $vehicles = FleetVehicle::forClient($clientId)->get()->keyBy('id');
        $maint = FleetMaintenance::forClient($clientId)->where('service_date', '>=', $from)
            ->where('is_draft', false)->get(['vehicle_id', 'total_cost', 'service_km'])->groupBy('vehicle_id');
        $fuel = FleetFuelLog::whereIn('vehicle_id', $vehicles->keys())->where('refuel_date', '>=', $from)
            ->get(['vehicle_id', 'cost', 'km_at_refuel'])->groupBy('vehicle_id');

        $rows = $vehicles->map(function ($v) use ($maint, $fuel) {
            $m = $maint->get($v->id, collect());
            $f = $fuel->get($v->id, collect());
            $gasto = (float) $m->sum('total_cost') + (float) $f->sum('cost');
            $odo = $m->pluck('service_km')->merge($f->pluck('km_at_refuel'))->filter(fn($k) => $k > 0);
            $km = $odo->count() >= 2 ? (int) ($odo->max() - $odo->min()) : 0;
            return [
                'id'         => $v->id,
                'name'       => $v->display_name,
                'plates'     => $v->plates,
                'gasto'      => round($gasto, 2),
                'km'         => $km,
                'costo_km'   => $km > 0 ? round($gasto / $km, 2) : null,
                'atipico'    => false,
            ];
        })->values();

        $vals = $rows->pluck('costo_km')->filter(fn($c) => $c !== null)->sort()->values();
        $n = $vals->count();
        $umbral = null;
        if ($n >= 4) {
            $q = fn($p) => $vals[(int) floor(($n - 1) * $p)];
            $umbral = $q(0.75) + 1.5 * ($q(0.75) - $q(0.25));
        } elseif ($n >= 2) {
            $umbral = $vals[(int) floor(($n - 1) / 2)] * 1.5;
        }
        $rows = $rows->map(function ($r) use ($umbral) {
            $r['atipico'] = $umbral !== null && $r['costo_km'] !== null && $r['costo_km'] > $umbral;
            return $r;
        })->sortByDesc(fn($r) => $r['costo_km'] ?? -1)->values();

        return response()->json([
            'months' => $months, 'umbral' => $umbral !== null ? round($umbral, 2) : null,
            'ranking' => $rows,
        ]);
    }

    public function alertas(): JsonResponse
    {
        $this->autorizar('fleet.view');

        $clientId = $this->clientId();

        $docs = FleetDocument::with('vehicle')
            ->forClient($clientId)
            ->whereNotNull('expiration_date')
            ->where('expiration_date', '<=', now()->addDays(30))
            ->orderBy('expiration_date')
            ->get()
            ->map(fn($d) => [
                'type'       => 'document',
                'vehicle_id' => $d->vehicle_id,
                'vehicle'    => $d->vehicle?->display_name,
                'doc_type'   => $d->document_type,
                'expiration' => $d->expiration_date,
                'days'       => $d->days_until_expiration,
                'status'     => $d->status,
            ]);

        $maintenances = FleetMaintenance::with('vehicle')
            ->forClient($clientId)
            ->whereNotNull('next_service_date')
            ->where('next_service_date', '<=', now()->addDays(30))
            ->orderBy('next_service_date')
            ->get()
            ->map(fn($m) => [
                'type'       => 'maintenance',
                'vehicle_id' => $m->vehicle_id,
                'vehicle'    => $m->vehicle?->display_name,
                'days'       => now()->diffInDays($m->next_service_date, false),
            ]);

        return response()->json(['alertas' => $docs->merge($maintenances)->sortBy('days')->values()]);
    }
}
