<?php

namespace App\Modules\Addons\GestionRed\Controllers\Router;

use App\Http\Controllers\Controller;
use App\Jobs\CreateClientWithServiceJob;
use App\Modules\Addons\GestionRed\Repositories\MikrotikSyncRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Item roadmap #677: dashboard read-only de servicios con sync Mikrotik
 * pending/failed + botón manual "Reintentar sync" por fila.
 * Reusa la misma llamada de despacho que MikrotikReintentarSyncCommand
 * (item #676) — no duplica lógica de reintento.
 */
class MikrotikSyncController extends Controller
{
    public function __construct(private MikrotikSyncRepository $repository)
    {
    }

    public function index()
    {
        return view('addon-gestion-red::mikrotik-sync.index');
    }

    public function servicios(Request $request)
    {
        $status = $request->query('status');
        if ($status !== null && !in_array($status, ['pending', 'failed'], true)) {
            $status = null;
        }

        $items = $this->repository->pendientesOFallidos($status);

        return response()->json([
            'items' => $items,
            'total' => count($items),
        ]);
    }

    public function reintentar(Request $request, string $tipo, int $id)
    {
        $service = $this->repository->encontrar($tipo, $id);

        if (!$service) {
            return response()->json(['message' => 'Servicio no encontrado.'], 404);
        }

        CreateClientWithServiceJob::dispatch($service, 'App\Models\Internet');

        Log::channel('single')->info(
            "mikrotik-sync-dashboard: reintento manual despachado por " . ($request->user()->id ?? 'desconocido')
            . " para {$tipo}#{$id}."
        );

        return response()->json(['message' => 'Reintento despachado.']);
    }
}
