<?php

namespace App\Modules\Addons\MapaRed\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MapaRed\Models\MapaRedEnlaceServicio;
use Illuminate\Http\Request;

/**
 * MR-14 (item #950) — enlace de servicio cliente/ONT ↔ puerto de NAP ↔ hilo. El trazo
 * extremo a extremo (grafo dirigido) es MR-16 (#952); esto solo persiste y lista la costura.
 */
class EnlacesServicioController extends Controller
{
    public function porNap(Request $request)
    {
        $data = $request->validate([
            'puertable_type' => 'required|string',
            'puertable_id' => 'required|integer',
        ]);

        $enlaces = MapaRedEnlaceServicio::porNap($data['puertable_type'], (int) $data['puertable_id']);

        return response()->json([
            'total' => $enlaces->count(),
            'por_estado' => $enlaces->countBy('estado'),
            'enlaces' => $enlaces->values(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'cliente_nombre' => 'required|string',
            'cliente_numero_contrato' => 'nullable|string',
            'ont_serie' => 'required|string',
            'puerto_nap_id' => 'required|integer|exists:mapared_puertos,id',
            'hilo_id' => 'nullable|integer|exists:mapared_hilos,id',
            'fecha_alta' => 'required|date',
            'estado' => 'sometimes|in:' . implode(',', MapaRedEnlaceServicio::ESTADOS),
            'observaciones' => 'nullable|string',
        ]);

        $enlace = MapaRedEnlaceServicio::create($data);

        return response()->json($enlace, 201);
    }

    public function update(Request $request, $id)
    {
        $enlace = MapaRedEnlaceServicio::findOrFail($id);

        $data = $request->validate([
            'estado' => 'sometimes|in:' . implode(',', MapaRedEnlaceServicio::ESTADOS),
            'observaciones' => 'sometimes|nullable|string',
        ]);

        $enlace->fill($data);
        $enlace->save();

        return response()->json($enlace);
    }
}
