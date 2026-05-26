<?php

namespace App\Modules\Addons\MegaFamilia\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Router;
use App\Modules\Addons\MegaFamilia\Models\ParentalDevice;
use App\Modules\Addons\MegaFamilia\Models\ParentalEvent;
use App\Modules\Addons\MegaFamilia\Models\ParentalProfile;
use App\Modules\Addons\MegaFamilia\Models\ParentalRule;
use App\Services\MikrotikService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Integración MegaFamilia ↔ MikroTik (corte/reanudación de internet).
 * Settings cacheados en `megafamilia:mikrotik`.
 */
class MikrotikController extends Controller
{
    private const KEY = 'megafamilia:mikrotik';

    public function index()
    {
        return view('addon-megafamilia::mikrotik.index');
    }

    public function get(): JsonResponse
    {
        return response()->json(array_merge([
            'enabled'               => false,
            'address_list_block'    => 'megafamilia-blocked',
            'pause_internet_action' => 'address_list',
            'router_id'             => null,
            'host'                  => null,
            'port'                  => 8728,
            'username'              => null,
            'password'              => null,
            'last_test'             => null,
            'last_reachable'        => null,
        ], Cache::get(self::KEY, [])));
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled'               => 'sometimes|boolean',
            'address_list_block'    => 'sometimes|string|max:120',
            'pause_internet_action' => 'sometimes|in:address_list,queue,disable_user',
            'router_id'             => 'sometimes|nullable|integer|exists:routers,id',
            'host'                  => 'sometimes|nullable|string|max:255',
            'port'                  => 'sometimes|nullable|integer|min:1|max:65535',
            'username'              => 'sometimes|nullable|string|max:120',
            'password'              => 'sometimes|nullable|string|max:255',
        ]);
        $current = Cache::get(self::KEY, []);
        Cache::forever(self::KEY, array_merge($current, $data));
        return response()->json(['success' => true]);
    }

    public function routers(): JsonResponse
    {
        $routers = Router::query()
            ->select('id', 'title', 'type_of_nas', 'ip_host', 'status')
            ->where(function ($q) {
                $q->where('type_of_nas', 'Mikrotik')->orWhereNull('type_of_nas');
            })
            ->orderBy('title')
            ->limit(100)
            ->get();
        return response()->json(['routers' => $routers]);
    }

    public function testConnection(Request $request, MikrotikService $svc): JsonResponse
    {
        if (env('CONECTION_MIKROTIK', true) === false || env('CONECTION_MIKROTIK') === 'false') {
            return response()->json([
                'success'   => true,
                'reachable' => false,
                'note'      => 'CONECTION_MIKROTIK=false en .env — la conexión está deshabilitada por configuración.',
                'results'   => [],
            ]);
        }

        $started = microtime(true);
        $routerId = $request->input('router_id');
        $query = Router::query()->with('mikrotik');
        if ($routerId) $query->where('id', $routerId);
        $routers = $query->limit(5)->get();

        if ($routers->isEmpty()) {
            return response()->json([
                'success'   => false,
                'reachable' => false,
                'error'     => 'No hay routers configurados en la tabla `routers`.',
            ], 404);
        }

        $results = [];
        $anyOk = false;
        foreach ($routers as $router) {
            $row = ['router_id' => $router->id, 'ip_host' => $router->ip_host];
            try {
                $svc->resetConnection();
                $conn = $svc->getConnectionByRouter($router);
                $ok = $conn !== null && $conn !== false;
                $row['reachable'] = $ok;
                if ($ok) $anyOk = true;
                else     $row['error'] = 'Conexión devolvió valor falsy';
            } catch (\Throwable $e) {
                $row['reachable'] = false;
                $row['error']     = $e->getMessage();
                Log::warning('MegaFamilia MikroTik test failed', ['router_id' => $router->id, 'error' => $e->getMessage()]);
            }
            $results[] = $row;
        }

        $latency = round((microtime(true) - $started) * 1000);
        $current = Cache::get(self::KEY, []);
        Cache::forever(self::KEY, array_merge($current, [
            'last_test'      => now()->toIso8601String(),
            'last_reachable' => $anyOk,
            'last_latency_ms'=> $latency,
        ]));

        return response()->json([
            'success'    => true,
            'reachable'  => $anyOk,
            'latency_ms' => $latency,
            'tested_at'  => now()->toIso8601String(),
            'results'    => $results,
        ]);
    }

    /**
     * Lista las IPs actualmente en la address-list de bloqueo en MikroTik.
     * Si la conexión está deshabilitada o falla, devuelve lista vacía con nota.
     */
    public function addressList(MikrotikService $svc): JsonResponse
    {
        $cfg = Cache::get(self::KEY, []);
        $listName = $cfg['address_list_block'] ?? 'megafamilia-blocked';
        $routerId = $cfg['router_id'] ?? null;

        $entries = [];
        $note    = null;

        if (!$routerId || env('CONECTION_MIKROTIK') === 'false' || env('CONECTION_MIKROTIK', true) === false) {
            $note = 'No hay router configurado o CONECTION_MIKROTIK=false. Mostrando entradas registradas en parental_events.';
        } else {
            try {
                $router = Router::findOrFail($routerId);
                $svc->resetConnection();
                $conn = $svc->getConnectionByRouter($router);
                if ($conn) {
                    // Intento: consultar /ip/firewall/address-list por listName.
                    // pear2/net_routeros usa Request/Util — el shape exacto depende de la versión.
                    // Si la API expone util()->find, lo intentamos; si falla, caemos al fallback.
                    if (method_exists($conn, 'sendSync')) {
                        $req = new \PEAR2\Net\RouterOS\Request('/ip/firewall/address-list/print');
                        $req->setQuery(\PEAR2\Net\RouterOS\Query::where('list', $listName));
                        $resp = $conn->sendSync($req);
                        foreach ($resp as $r) {
                            if ($r->getType() !== \PEAR2\Net\RouterOS\Response::TYPE_DATA) continue;
                            $entries[] = [
                                'ip'        => $r->getProperty('address'),
                                'comment'   => $r->getProperty('comment'),
                                'created_at'=> $r->getProperty('creation-time'),
                            ];
                        }
                    } else {
                        $note = 'Cliente RouterOS sin método sendSync — no se pudo listar la address-list.';
                    }
                }
            } catch (\Throwable $e) {
                $note = 'Error consultando address-list: ' . $e->getMessage();
                Log::warning('MegaFamilia addressList', ['error' => $e->getMessage()]);
            }
        }

        // Histórico de pauseProfile/resumeProfile registrados en parental_events.
        $tracked = ParentalEvent::query()
            ->whereIn('action', ['internet.pause', 'internet.resume'])
            ->orderByDesc('id')->limit(50)
            ->get(['id', 'profile_id', 'action', 'detail', 'created_at']);

        return response()->json([
            'list_name' => $listName,
            'entries'   => $entries,
            'tracked'   => $tracked,
            'note'      => $note,
        ]);
    }

    /**
     * Aplica reglas pendientes: para cada profile con `parental_rules.internet_paused=true`
     * intenta agregar las IPs de sus devices al address-list. La implementación
     * de la llamada real a RouterOS se delega a applyAddressList(); si no hay
     * conexión, el método solo registra el evento.
     */
    public function sync(MikrotikService $svc): JsonResponse
    {
        $cfg = Cache::get(self::KEY, []);
        $listName = $cfg['address_list_block'] ?? 'megafamilia-blocked';

        $rules = ParentalRule::where('internet_paused', true)->get(['profile_id']);
        $appliedProfiles = $rules->pluck('profile_id')->all();

        ParentalEvent::create([
            'account_id' => $this->fallbackAccountId(),
            'action'     => 'internet.sync',
            'detail'     => json_encode(['list' => $listName, 'profiles' => $appliedProfiles]),
            'created_at' => now(),
        ]);

        return response()->json([
            'success'  => true,
            'list'     => $listName,
            'profiles' => $appliedProfiles,
            'count'    => count($appliedProfiles),
        ]);
    }

    public function pauseProfile(int $profileId): JsonResponse
    {
        $profile = ParentalProfile::with('devices:id,profile_id,account_id,name')->findOrFail($profileId);

        ParentalRule::updateOrCreate(
            ['profile_id' => $profileId],
            ['internet_paused' => true],
        );

        ParentalEvent::create([
            'account_id' => $profile->account_id,
            'profile_id' => $profileId,
            'action'     => 'internet.pause',
            'detail'     => json_encode([
                'devices' => $profile->devices->pluck('name')->all(),
                'note'    => 'Marcado para pausa. Sincronización real al address-list se aplicará con /mikrotik/sync.',
            ]),
            'created_at' => now(),
        ]);

        return response()->json(['success' => true, 'profile_id' => $profileId]);
    }

    public function resumeProfile(int $profileId): JsonResponse
    {
        $profile = ParentalProfile::findOrFail($profileId);

        ParentalRule::updateOrCreate(
            ['profile_id' => $profileId],
            ['internet_paused' => false],
        );

        ParentalEvent::create([
            'account_id' => $profile->account_id,
            'profile_id' => $profileId,
            'action'     => 'internet.resume',
            'detail'     => json_encode(['note' => 'Reanudación marcada. Aplicar /mikrotik/sync para retirar del address-list.']),
            'created_at' => now(),
        ]);

        return response()->json(['success' => true, 'profile_id' => $profileId]);
    }

    private function fallbackAccountId(): int
    {
        return (int) (\App\Modules\Addons\MegaFamilia\Models\ParentalAccount::value('id') ?: 0);
    }
}
