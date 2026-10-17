<?php

namespace App\Modules\Core\Clientes\Controllers;

use App\Models\ClientHistorialCambio;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * FR-04 (#538): historial de cambios de estado y de contrato del cliente (solo lectura). Mismo
 * acceso que el resto de la ficha (client_view_client en route_permission).
 */
class ClientHistorialCambiosController extends Controller
{
    public function index(int $id): JsonResponse
    {
        $cambios = ClientHistorialCambio::with('user:id,name,father_last_name')
            ->where('client_id', $id)->orderByDesc('id')->limit(100)->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'fecha' => optional($c->created_at)->format('d/m/Y H:i'),
                'campo' => $c->campo,
                'antes' => $c->valor_anterior,
                'despues' => $c->valor_nuevo,
                'origen' => $c->origen,
                'quien' => $c->user ? trim($c->user->name . ' ' . ($c->user->father_last_name ?? '')) : null,
                'motivo' => $c->motivo,
            ]);

        return response()->json(['cambios' => $cambios]);
    }
}
