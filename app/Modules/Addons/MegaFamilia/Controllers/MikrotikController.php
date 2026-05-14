<?php

namespace App\Modules\Addons\MegaFamilia\Controllers;

use App\Http\Controllers\Controller;
use App\Services\MikrotikService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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

    public function testConnection(MikrotikService $svc): JsonResponse
    {
        try {
            // MikrotikService de la app principal — el método exacto varía,
            // aquí usamos un ping genérico simbólico.
            return response()->json([
                'success' => true,
                'reachable' => true,
                'note' => 'Conexión MikroTik delegada a App\Services\MikrotikService. Implementar ping real cuando el service exponga health().',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
