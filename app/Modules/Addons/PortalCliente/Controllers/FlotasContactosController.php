<?php

namespace App\Modules\Addons\PortalCliente\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Flotas\Models\FleetClientAlertContact;
use App\Modules\Addons\Flotas\Models\FleetClientAlertDelivery;
use App\Modules\Addons\Flotas\Models\FleetClientWhatsappUsage;
use App\Modules\Addons\Flotas\Services\FleetSubscriptionService;
use App\Services\Tenant\CurrentClientResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contactos de alerta del cliente + bitácora de envíos (item #672, Flotas Clientes P5c).
 * Toda consulta pasa por own(): client_id del resolver, nunca uno que mande el navegador.
 * La bitácora expone solo canal/destino propio/estado/motivo (nada interno de Meganet).
 */
class FlotasContactosController extends Controller
{
    public const CANALES = ['portal', 'email', 'whatsapp'];

    public function __construct(
        private CurrentClientResolver $resolver,
        private FleetSubscriptionService $subs,
    ) {
    }

    public function pagina(Request $request)
    {
        if ($request->expectsJson()) {
            return $this->index();
        }
        $this->gate();

        return view('addon-portal-cliente::flotas_contactos', ['cmi' => auth('cliente')->user()]);
    }

    public function index(): JsonResponse
    {
        $clientId = $this->gate();
        $periodo = now()->format('Y-m');
        $uso = FleetClientWhatsappUsage::where('client_id', $clientId)->where('periodo', $periodo)->first();

        return response()->json([
            'contactos' => $this->own($clientId)->orderByDesc('es_principal')->orderBy('nombre')->get()->map(fn ($c) => $this->present($c)),
            'limite'    => FleetClientAlertContact::maxPorCliente(),
            'tipos'     => FlotasReglasController::TIPOS,
            'whatsapp'  => ['enviados' => (int) ($uso->enviados ?? 0), 'tope' => (int) config('flotas.alertas_cliente.whatsapp_tope_mensual', 100)],
            'bitacora'  => FleetClientAlertDelivery::where('client_id', $clientId)->orderByDesc('id')->limit(100)->get()->map(fn ($d) => [
                'id'      => $d->id,
                'fecha'   => $d->created_at?->toIso8601String(),
                'canal'   => $d->channel,
                'destino' => $d->destination,
                'estado'  => $d->status,
                'motivo'  => $d->motivo,
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $clientId = $this->gate();
        $d = $this->validar($request);
        abort_if($this->own($clientId)->count() >= FleetClientAlertContact::maxPorCliente(), 422, 'Alcanzaste el máximo de contactos de alerta.');
        $c = FleetClientAlertContact::create($d + ['client_id' => $clientId]);
        $this->unicoPrincipal($clientId, $c);

        return response()->json(['contacto' => $this->present($c)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $clientId = $this->gate();
        $c = $this->own($clientId)->findOrFail($id);
        $c->update($this->validar($request));
        $this->unicoPrincipal($clientId, $c);

        return response()->json(['contacto' => $this->present($c->fresh())]);
    }

    public function destroy(int $id): JsonResponse
    {
        $clientId = $this->gate();
        $c = $this->own($clientId)->findOrFail($id);
        abort_if($c->es_principal && $this->own($clientId)->count() > 1, 422, 'Marca otro contacto como principal antes de borrar este.');
        $c->delete();

        return response()->json(['ok' => true]);
    }

    private function validar(Request $request): array
    {
        $d = $request->validate([
            'nombre'         => 'required|string|max:120',
            'email'          => 'nullable|email|max:190',
            'telefono'       => 'nullable|string|max:30',
            'es_principal'   => 'boolean',
            'channels'       => 'nullable|array',
            'channels.*'     => 'in:' . implode(',', self::CANALES),
            'tipos_alerta'   => 'nullable|array',
            'tipos_alerta.*' => 'in:' . implode(',', FlotasReglasController::TIPOS),
            'silencio_desde' => 'nullable|date_format:H:i',
            'silencio_hasta' => 'nullable|date_format:H:i|required_with:silencio_desde',
            'active'         => 'boolean',
        ]);
        $canales = $d['channels'] ?? [];
        abort_if(in_array('email', $canales, true) && empty($d['email']), 422, 'Para avisar por correo captura el email.');
        abort_if(in_array('whatsapp', $canales, true) && empty($d['telefono']), 422, 'Para avisar por WhatsApp captura el teléfono.');
        if (empty($d['silencio_desde'])) {
            $d['silencio_desde'] = $d['silencio_hasta'] = null;
        }

        return $d;
    }

    private function unicoPrincipal(int $clientId, FleetClientAlertContact $c): void
    {
        if ($c->es_principal) {
            $this->own($clientId)->where('id', '!=', $c->id)->update(['es_principal' => false]);
        }
    }

    private function present(FleetClientAlertContact $c): array
    {
        return $c->only(['id', 'nombre', 'email', 'telefono', 'es_principal', 'channels', 'tipos_alerta', 'active'])
            + ['silencio_desde' => $c->silencio_desde ? substr($c->silencio_desde, 0, 5) : null,
               'silencio_hasta' => $c->silencio_hasta ? substr($c->silencio_hasta, 0, 5) : null];
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
        return FleetClientAlertContact::query()->forClient($clientId);
    }
}
