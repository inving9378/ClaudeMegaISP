<?php

namespace App\Modules\Addons\MegaFamilia\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Router;
use App\Services\MikrotikService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Integración MegaFamilia ↔ MikroTik (corte de internet por reglas
 * parentales). Settings cacheados en `megafamilia:mikrotik`.
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
        return response()->json(Cache::get(self::KEY, [
            'enabled' => false,
            'address_list_block' => 'megafamilia-blocked',
            'pause_internet_action' => 'address_list',
        ]));
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled' => 'sometimes|boolean',
            'address_list_block' => 'sometimes|string',
            'pause_internet_action' => 'sometimes|in:address_list,queue,disable_user',
        ]);
        $current = Cache::get(self::KEY, []);
        Cache::forever(self::KEY, array_merge($current, $data));
        return response()->json(['success' => true]);
    }

    /**
     * Prueba la conexión a uno o más routers MikroTik usando
     * MikrotikService::getConnectionByRouter(), que abre una conexión TCP
     * vía pear2/net_routeros. Itera sobre los routers de la tabla
     * `routers` (limitado a 5 para no colgar el request) y reporta el
     * estado de cada uno.
     */
    public function testConnection(Request $request, MikrotikService $svc): JsonResponse
    {
        if (env('CONECTION_MIKROTIK', true) === false || env('CONECTION_MIKROTIK') === 'false') {
            return response()->json([
                'success' => true,
                'reachable' => false,
                'note' => 'CONECTION_MIKROTIK=false en .env — la conexión está deshabilitada por configuración.',
                'results' => [],
            ]);
        }

        $routerId = $request->input('router_id');
        $query = Router::query()->with('mikrotik');
        if ($routerId) {
            $query->where('id', $routerId);
        }
        $routers = $query->limit(5)->get();

        if ($routers->isEmpty()) {
            return response()->json([
                'success' => false,
                'reachable' => false,
                'error' => 'No hay routers configurados en la tabla `routers`.',
            ], 404);
        }

        $results = [];
        $anyOk = false;
        foreach ($routers as $router) {
            $row = ['router_id' => $router->id, 'ip_host' => $router->ip_host];
            try {
                $svc->resetConnection(); // fresh socket por router
                $conn = $svc->getConnectionByRouter($router);
                $ok = $conn !== null && $conn !== false;
                $row['reachable'] = $ok;
                if ($ok) {
                    $anyOk = true;
                } else {
                    $row['error'] = 'Conexión devolvió valor falsy';
                }
            } catch (\Throwable $e) {
                $row['reachable'] = false;
                $row['error'] = $e->getMessage();
                Log::warning('MegaFamilia MikroTik test failed', [
                    'router_id' => $router->id,
                    'error' => $e->getMessage(),
                ]);
            }
            $results[] = $row;
        }

        return response()->json([
            'success' => true,
            'reachable' => $anyOk,
            'tested_at' => now()->toIso8601String(),
            'results' => $results,
        ]);
    }
}
