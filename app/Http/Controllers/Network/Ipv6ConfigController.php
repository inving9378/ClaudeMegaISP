<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Models\ClientInternetService;
use App\Models\Router;
use App\Services\Ipv6\Ipv6AddressPlanCalculator;
use App\Services\Ipv6\Ipv6CommandGenerator;
use App\Services\Ipv6\Ipv6TopologyProposer;
use App\Services\Ipv6\MikrotikIpv6Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * IPv6 1.7a (item #999, épica #811 → #951/#950) — pantalla standalone de
 * configuración IPv6. SOLO LECTURA hacia el router (detección de versión +
 * descubrimiento de topología marcada por MgNet-IPv6) + cálculo puro de un
 * plan de direccionamiento. NO depende de #949 (módulo/permisos/tabla
 * `ipv6_despliegues`, aún sin ejecutar — sub-items 984/985/986 pendientes):
 * reusa `MikrotikIpv6Client` (#951), `Ipv6AddressPlanCalculator` y
 * `Ipv6CommandGenerator` (#950), ya existentes y cubiertos por sus propios
 * tests unitarios. Ningún endpoint aquí escribe en el router.
 */
class Ipv6ConfigController extends Controller
{
    public function index()
    {
        return view('network.ipv6-config');
    }

    /** IPv6 Fase 5.3b (item #1070) — pantalla del simulador de renumeración (4 fases RFC4192). */
    public function simulador()
    {
        return view('network.ipv6-renumbering-simulador');
    }

    /** GET routers registrados (sin exponer credenciales del Mikrotik). */
    public function routers(): JsonResponse
    {
        $conteos = ClientInternetService::whereIn('estado', ['Activo', 'Activado'])
            ->selectRaw('router_id, count(*) as total')
            ->groupBy('router_id')
            ->pluck('total', 'router_id');

        $routers = Router::with('mikrotik')
            ->get()
            ->map(function (Router $router) use ($conteos) {
                return [
                    'id' => $router->id,
                    'title' => $router->title,
                    'ip_host' => $router->ip_host,
                    'type_of_nas' => $router->type_of_nas,
                    'conectable' => $this->esConectable($router),
                    // Conteo real de clientes de internet activos sobre este router
                    // (client_internet_services.estado in Activo/Activado). Fuente
                    // obvia disponible; NO se inventa si el router simplemente no
                    // tiene filas (queda en 0, valor real, no placeholder).
                    'clientes_activos' => (int) ($conteos[$router->id] ?? 0),
                ];
            })
            ->values();

        return response()->json(['routers' => $routers]);
    }

    /** POST detectar versión (lectura, /system resource print). Frontend hace fallback manual si falla. */
    public function detectarVersion(Request $request): JsonResponse
    {
        $data = $request->validate([
            'router_id' => 'required|integer|exists:routers,id',
            'family_override' => 'nullable|string|max:32',
        ]);

        [$router, $error] = $this->resolverRouterConectable((int) $data['router_id']);
        if ($error) {
            return $error;
        }

        $client = new MikrotikIpv6Client();

        try {
            $connection = $this->conectar($client, $router);
            if (!$connection) {
                return response()->json(['ok' => false, 'error' => 'No se pudo conectar al router. Usa el selector manual de respaldo.'], 502);
            }

            $version = $client->detectVersion($connection, $data['family_override'] ?? null);

            return response()->json(['ok' => true, 'version' => $version]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'error' => 'Error al consultar el router: ' . $e->getMessage()], 502);
        }
    }

    /** POST mapear zonas (lectura: VLANs/perfiles PPP/PPPoE/IPv6 ya asignados + propuesta pura). */
    public function mapearZonas(Request $request): JsonResponse
    {
        $data = $request->validate([
            'router_id' => 'required|integer|exists:routers,id',
            'marker' => 'nullable|string|max:64',
            'family_override' => 'nullable|string|max:32',
        ]);

        [$router, $error] = $this->resolverRouterConectable((int) $data['router_id']);
        if ($error) {
            return $error;
        }

        $client = new MikrotikIpv6Client();

        try {
            $connection = $this->conectar($client, $router);
            if (!$connection) {
                return response()->json(['ok' => false, 'error' => 'No se pudo conectar al router.'], 502);
            }

            $topologia = $client->discoverTopology(
                $connection,
                $data['marker'] ?? Ipv6TopologyProposer::DEFAULT_MARKER,
                $data['family_override'] ?? null
            );

            return response()->json(['ok' => true, 'topology' => $topologia]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'error' => 'Error al consultar el router: ' . $e->getMessage()], 502);
        }
    }

    /**
     * POST vista previa del plan de direccionamiento — cálculo PURO
     * (Ipv6AddressPlanCalculator + Ipv6CommandGenerator), nunca toca el router.
     */
    public function vistaPrevia(Request $request): JsonResponse
    {
        $data = $request->validate([
            'prefijo' => 'required|string',
            'clientes_actuales' => 'required|integer|min:0',
            'margen_crecimiento' => 'required|numeric|gt:0',
            'zonas' => 'required|array|min:1',
            'zonas.*' => 'required|string',
            'version' => 'required|string',
        ]);

        try {
            $plan = Ipv6AddressPlanCalculator::calcular(
                $data['prefijo'],
                $data['clientes_actuales'],
                (float) $data['margen_crecimiento'],
                $data['zonas']
            );

            $resultado = (new Ipv6CommandGenerator())->generarParaPlan($plan, $data['version']);

            return response()->json([
                'ok' => true,
                'plan' => $plan,
                'driver' => $resultado['driver'],
                'comandos' => $resultado['comandos'],
                'advertencias' => $resultado['advertencias'],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    private function esConectable(Router $router): bool
    {
        return $router->isMikrotik()
            && $router->hasMikrotik()
            && !empty($router->mikrotik->login_api)
            && !empty($router->mikrotik->port_api);
    }

    /** @return array{0: ?Router, 1: ?JsonResponse} */
    private function resolverRouterConectable(int $routerId): array
    {
        $router = Router::with('mikrotik')->find($routerId);

        if (!$router || !$this->esConectable($router)) {
            return [null, response()->json(['ok' => false, 'error' => 'Este router no tiene credenciales Mikrotik configuradas.'], 422)];
        }

        return [$router, null];
    }

    private function conectar(MikrotikIpv6Client $client, Router $router)
    {
        return $client->connect(
            $router->ip_host,
            $router->mikrotik->login_api,
            $router->mikrotik->password_api,
            $router->mikrotik->port_api
        );
    }
}
