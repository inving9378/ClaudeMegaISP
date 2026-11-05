<?php

namespace App\Modules\Addons\PortalCliente\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Flotas\Models\FleetGeofenceEvent;
use App\Modules\Addons\Flotas\Services\FleetSubscriptionService;
use App\Services\Tenant\CurrentClientResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Historial de alertas de geocerca del cliente (item #645, Flotas Clientes P4d).
 * Toda consulta pasa por own(): eventos de unidades con client_id = el del resolver, así que
 * las alertas de otro cliente o de la flota interna de Meganet nunca se alcanzan.
 */
class FlotasAlertasController extends Controller
{
    public function __construct(
        private CurrentClientResolver $resolver,
        private FleetSubscriptionService $subs,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $clientId = $this->gate();
        $d = $request->validate([
            'vehicle_id' => 'nullable|integer',
            'desde'      => 'nullable|date',
            'hasta'      => 'nullable|date|after_or_equal:desde',
            'estado'     => 'nullable|in:nueva,vista',
        ]);

        $q = $this->own($clientId)->with(['geofence:id,name', 'vehicle:id,plates,brand,model']);
        if (!empty($d['vehicle_id'])) {
            $q->where('vehicle_id', $d['vehicle_id']);
        }
        if (!empty($d['desde'])) {
            $q->where('occurred_at', '>=', \Carbon\Carbon::parse($d['desde'])->startOfDay());
        }
        if (!empty($d['hasta'])) {
            $q->where('occurred_at', '<=', \Carbon\Carbon::parse($d['hasta'])->endOfDay());
        }
        if (($d['estado'] ?? null) === 'nueva') {
            $q->whereNull('visto_at');
        } elseif (($d['estado'] ?? null) === 'vista') {
            $q->whereNotNull('visto_at');
        }

        return response()->json([
            'nuevas'  => $this->own($clientId)->whereNull('visto_at')->count(),
            'alertas' => $q->orderByDesc('occurred_at')->limit(200)->get()->map(fn ($e) => [
                'id'       => $e->id,
                'tipo'     => $e->event_type === 'enter' ? 'Entró a' : 'Salió de',
                'geocerca' => $e->geofence?->name,
                'unidad'   => trim(($e->vehicle?->plates ?? '') . ' ' . ($e->vehicle?->brand ?? '') . ' ' . ($e->vehicle?->model ?? '')),
                'vehicle_id' => $e->vehicle_id,
                'fecha'    => $e->occurred_at?->toIso8601String(),
                'vista'    => $e->visto_at !== null,
            ]),
        ]);
    }

    /** Marca como vistas todas las alertas nuevas del cliente, o solo una si se indica {id}. */
    public function marcarVista(Request $request, ?int $id = null): JsonResponse
    {
        $clientId = $this->gate();
        $q = $this->own($clientId)->whereNull('visto_at');
        if ($id !== null) {
            abort_unless($this->own($clientId)->whereKey($id)->exists(), 404);
            $q->whereKey($id);
        }
        $n = $q->update(['visto_at' => now()]);

        return response()->json(['ok' => true, 'marcadas' => $n]);
    }

    private function gate(): int
    {
        $clientId = $this->resolver->resolve();
        abort_unless($clientId, 403, 'Sesión sin cliente.');
        abort_unless($this->subs->clientHasAccess($clientId), 403, 'Tu servicio de Flotas no está activo. Solicita su activación.');

        return (int) $clientId;
    }

    private function own(int $clientId)
    {
        return FleetGeofenceEvent::query()->forClient($clientId);
    }
}
