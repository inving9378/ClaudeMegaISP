<?php

namespace App\Modules\Addons\PortalCliente\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Flotas\Models\FleetVehicle;
use App\Modules\Addons\Flotas\Services\FleetSubscriptionService;
use App\Services\Tenant\CurrentClientResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Panel "Mi Flota" en el portal de cliente (guard `cliente`). Lectura aquí; las escrituras viven en FlotasGestionController (#621).
 *
 * client_id viene de CurrentClientResolver::resolve() (rama guard cliente) y TODA
 * consulta se scopea con ->forClient($clientId). Los vehículos internos de Meganet
 * (client_id NULL) jamás aparecen: forClient($clientId concreto) filtra
 * WHERE client_id = $clientId. Reusa FleetSubscriptionService (no duplica lógica).
 */
class FlotasController extends Controller
{
    public function index(CurrentClientResolver $resolver, FleetSubscriptionService $subs)
    {
        $clientId = $resolver->resolve();
        $cmi      = Auth::guard('cliente')->user();

        // Fail-closed: sin client_id resuelto no se expone nada.
        $subscription = $clientId ? $subs->forClient($clientId) : null;
        $vehicles     = $clientId
            ? FleetVehicle::forClient($clientId)
                ->orderBy('plates')
                ->get(['id', 'plates', 'brand', 'model', 'year', 'vehicle_type', 'status'])
            : collect();

        // Sin flota, o con su servicio de Flotas cancelado/vencido → estado vacío con CTA, NO un tablero
        // que falla (las APIs del portal exigen suscripción usable: middleware flotas.portal) (#657).
        if (! $subscription?->isUsable()) {
            return view('addon-portal-cliente::flotas', [
                'cmi'    => $cmi,
                'active' => false,
            ]);
        }

        return $this->vistaVue('dashboard', null, $clientId);
    }

    /** Alta de vehículo: monta el formulario Vue del panel (#657). */
    public function nuevo(CurrentClientResolver $resolver)
    {
        return $this->vistaVue('nuevo', null, $resolver->resolve());
    }

    /** Ficha del vehículo: el id lo pasa el blade; 404 si no es del cliente (fail-closed). */
    public function ficha(int $id, CurrentClientResolver $resolver)
    {
        $clientId = $resolver->resolve();
        abort_unless($clientId && FleetVehicle::forClient($clientId)->whereKey($id)->exists(), 404);

        return $this->vistaVue('ficha', $id, $clientId);
    }

    private function vistaVue(string $vista, ?int $vehicleId, $clientId)
    {
        return view('addon-portal-cliente::flotas_app', [
            'vista'      => $vista,
            'vehicleId'  => $vehicleId,
            'privacidad' => [
                'aceptada' => $this->privacidadAceptada($clientId),
                'texto'    => config('portalcliente.flotas_privacidad.texto'),
            ],
        ]);
    }

    /** Bitácora de notificaciones (#664): mismo componente del panel, datos por /portal/flotas/api/notificaciones-log. */
    public function notificaciones(CurrentClientResolver $resolver, FleetSubscriptionService $subs)
    {
        $clientId = $resolver->resolve();
        abort_unless($clientId, 403, 'Sesión sin cliente.');
        abort_unless($subs->clientHasAccess($clientId), 403, 'Tu servicio de Flotas no está activo. Solicita su activación.');

        return view('addon-portal-cliente::flotas_notificaciones');
    }

    /** Registra la aceptación del aviso de privacidad del rastreo (cliente, usuario, fecha, versión del texto). */
    public function aceptarPrivacidad(Request $request, CurrentClientResolver $resolver)
    {
        $clientId = $resolver->resolve();
        abort_unless($clientId, 403);

        if (! $this->privacidadAceptada($clientId)) {
            DB::table('portal_flotas_privacidad_aceptaciones')->insert([
                'client_id'  => $clientId,
                'usuario_id' => Auth::guard('cliente')->id(),
                'version'    => (string) config('portalcliente.flotas_privacidad.version'),
                'aceptado_at' => now(),
                'ip'         => $request->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json(['ok' => true]);
    }

    private function privacidadAceptada(?int $clientId): bool
    {
        return $clientId && DB::table('portal_flotas_privacidad_aceptaciones')
            ->where('client_id', $clientId)
            ->where('version', (string) config('portalcliente.flotas_privacidad.version'))
            ->exists();
    }
}
