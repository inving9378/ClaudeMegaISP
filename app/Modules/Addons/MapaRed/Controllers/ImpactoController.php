<?php

namespace App\Modules\Addons\MapaRed\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MapaRed\Services\MapaRedEnlaceMrrService;
use App\Modules\Addons\MapaRed\Services\RedGraphService;
use App\Modules\Core\Clientes\Models\ClientMainInformation;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * MR-17 Fase 3a (item roadmap #9990593) — "¿quién depende de esto?": dado un elemento de la
 * red física, hace fan-out aguas abajo (Fase 1/#9990552, `RedGraphService::fanOutDesde`) y
 * calcula el impacto en clientes/MRR de esos enlaces (Fase 2/#9990553,
 * `MapaRedEnlaceMrrService::calcular`). Solo lectura, sin persistencia.
 */
class ImpactoController extends Controller
{
    public function index(Request $request, RedGraphService $grafo, MapaRedEnlaceMrrService $mrr)
    {
        $data = $request->validate([
            'tipo' => 'required|in:cable,hilo,puerto,splitter,nap,mufa',
            'id' => 'required|integer',
        ]);

        try {
            $fanOut = $grafo->fanOutDesde($data['tipo'], (int) $data['id']);
        } catch (InvalidArgumentException|ModelNotFoundException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }

        $resultado = $mrr->calcular($fanOut['enlace_ids']);

        $totalSuspendidos = collect($resultado['detalle'])
            ->where('estado_cliente', ClientMainInformation::STATE_BLOCKED)
            ->count();

        return response()->json([
            'tipo' => $data['tipo'],
            'id' => (int) $data['id'],
            'enlace_ids' => $fanOut['enlace_ids'],
            'advertencias' => $fanOut['advertencias'],
            'total_clientes' => $resultado['total_clientes'],
            'clientes_sin_vincular' => $resultado['clientes_sin_vincular'],
            'mrr_total' => $resultado['mrr_total'],
            'total_suspendidos' => $totalSuspendidos,
            'detalle' => $resultado['detalle'],
        ]);
    }
}
