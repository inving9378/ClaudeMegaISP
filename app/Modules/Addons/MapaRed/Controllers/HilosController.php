<?php

namespace App\Modules\Addons\MapaRed\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MapaRed\Models\MapaRedHilo;
use Illuminate\Http\Request;

/**
 * MR-11 (item #947) — hilo como entidad propia. Listado/ocupación por cable y cambio de
 * estado (p.ej. marcar dañado). El consumo visual en el trazo del mapa es MR-16 (#952).
 */
class HilosController extends Controller
{
    public function porCable($cableId)
    {
        $hilos = MapaRedHilo::where('cable_id', $cableId)
            ->orderBy('buffer')
            ->orderBy('numero')
            ->get();

        $porEstado = $hilos->countBy('estado');

        return response()->json([
            'cable_id' => (int) $cableId,
            'total' => $hilos->count(),
            'por_estado' => [
                'libre' => $porEstado->get('libre', 0),
                'asignado' => $porEstado->get('asignado', 0),
                'dañado' => $porEstado->get('dañado', 0),
                'reservado' => $porEstado->get('reservado', 0),
            ],
            'buffers' => $hilos->groupBy('buffer')->map(function ($grupo) {
                return $grupo->values();
            }),
        ]);
    }

    public function update(Request $request, $id)
    {
        $hilo = MapaRedHilo::findOrFail($id);

        $data = $request->validate([
            'estado' => 'sometimes|in:' . implode(',', MapaRedHilo::ESTADOS),
            'observaciones' => 'sometimes|nullable|string',
        ]);

        $hilo->fill($data);
        $hilo->save();

        return response()->json($hilo);
    }
}
