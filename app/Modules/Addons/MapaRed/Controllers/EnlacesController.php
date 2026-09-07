<?php

namespace App\Modules\Addons\MapaRed\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MapaRed\Models\MapaRedEnlace;
use Illuminate\Http\Request;

/**
 * MR-23 fase 4a (item #9990518) — acción "Trazar" del mapa de red: enlace directo entre dos
 * nodos (`mapared_layers`). El prefijo /mapa-red/api/** ya está gateado en bloque por
 * `mapa_red_view` (config/route_permission.php); el gate fino de `mapa_red_trazar` va dentro
 * del controller, mismo patrón que `OLTsOnuController` (CLAUDE.md, Fase 3a-bis).
 */
class EnlacesController extends Controller
{
    public function store(Request $request)
    {
        if (! auth()->user()?->can('mapa_red_trazar')) {
            return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
        }

        $data = $request->validate([
            'nodo_origen_id' => 'required|integer|different:nodo_destino_id|exists:mapared_layers,id',
            'nodo_destino_id' => 'required|integer|exists:mapared_layers,id',
            'tipo' => 'sometimes|in:' . implode(',', MapaRedEnlace::TIPOS),
            'estado' => 'sometimes|in:' . implode(',', MapaRedEnlace::ESTADOS),
        ], [
            'nodo_origen_id.different' => 'El nodo de origen y el de destino no pueden ser el mismo.',
        ]);

        $yaExiste = MapaRedEnlace::query()
            ->where(function ($q) use ($data) {
                $q->where('nodo_origen_id', $data['nodo_origen_id'])
                    ->where('nodo_destino_id', $data['nodo_destino_id']);
            })
            ->orWhere(function ($q) use ($data) {
                $q->where('nodo_origen_id', $data['nodo_destino_id'])
                    ->where('nodo_destino_id', $data['nodo_origen_id']);
            })
            ->exists();

        if ($yaExiste) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe un enlace entre estos dos nodos.',
            ], 422);
        }

        $enlace = MapaRedEnlace::create($data);

        return response()->json($enlace, 201);
    }
}
