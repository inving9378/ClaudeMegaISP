<?php

namespace App\Modules\Addons\MapaRed\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MapaRed\Services\RedGraphService;

/** Item #1237 — lámpara: recorrido de la fibra de un cliente hasta la OLT (solo lectura). */
class LamparaController extends Controller
{
    public function __construct(private RedGraphService $grafo)
    {
    }

    public function cliente(int $clientId)
    {
        $puertos = $this->grafo->puertosDeCliente($clientId);
        if ($puertos === []) {
            return response()->json([
                'completa' => false,
                'motivo_corte' => 'El cliente no está dibujado en ninguna caja de servicio del mapa.',
                'origen' => null, 'tramos' => [], 'puntos' => [], 'cliente_id' => $clientId, 'puerto_id' => null,
            ]);
        }

        // Si el cliente aparece en varios puertos, se prefiere el que sí llega a la OLT.
        $mejor = null;
        foreach ($puertos as $pid) {
            $r = $this->grafo->trazarDiagramaDesdePuerto($pid);
            if ($r['completa']) {
                return response()->json($r);
            }
            $mejor ??= $r;
        }

        return response()->json($mejor);
    }
}
