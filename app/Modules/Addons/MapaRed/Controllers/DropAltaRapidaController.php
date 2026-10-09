<?php

namespace App\Modules\Addons\MapaRed\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MapaRed\Models\MapaRedLayer;
use App\Modules\Addons\MapaRed\Services\NomenclaturaService;
use App\Modules\Addons\MapaRed\Services\ZonaResolverService;
use Illuminate\Http\Request;

/**
 * MR-22 — Drop (acometida NAP -> cliente): alta rápida de la línea final de conexión,
 * análoga a CableAltaRapidaController/NapAltaRapidaController (MR-24d/e). Decisión de Irving
 * (sesión 2026-10-08): un drop ES una línea dibujada a mano del NAP al cliente — igual patrón
 * que cable/troncal, NO captura automática al activar un cliente (eso queda fuera de alcance).
 *
 * La capa "Drops" ya existía reservada en el frontend (LeafletMapRed.vue, CAPAS_MAPA_RED) con
 * un comentario explícito: "no es todavía una entidad propia en mapared_layers... 0 filas con
 * un dialog dedicado a drop". Este controller es justo esa entidad: MapaRedLayer dialog='drop'.
 *
 * Mismo encadenado de 2 pasos que CableAltaRapidaController (sin el paso de número de hilos —
 * un drop no se nombra por capacidad de fibra, D24 solo define NAP/Troncal; se extiende el
 * mismo patrón con un correlativo propio 'drop'):
 *   1) zona/proyecto por el punto medio del trazo (ZonaResolverService) — 422 si no hay zona.
 *   2) nombre siguiente (NomenclaturaService::siguienteDrop) -> D-{ZONA}-{N}.
 *   3) crea el MapaRedLayer (type=polyline, dialog=drop) — mismo shape que cable/troncal para
 *      que el resto del módulo (KML/GeoJSON ya construidos en MR-25, panel lateral, etc.) lo
 *      trate como cualquier otra polilínea sin cambios adicionales.
 */
class DropAltaRapidaController extends Controller
{
    public function __construct(private ZonaResolverService $zonaResolver)
    {
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'puntos' => 'required|array|min:2',
            'puntos.*.lat' => 'required|numeric',
            'puntos.*.lng' => 'required|numeric',
        ]);

        $puntos = array_values($data['puntos']);
        $medio = $puntos[(int) floor((count($puntos) - 1) / 2)];

        $zonaInfo = $this->zonaResolver->resolverPorPunto((float) $medio['lat'], (float) $medio['lng']);
        if (!$zonaInfo || !$zonaInfo['zona']) {
            return response()->json([
                'message' => 'No se pudo resolver una zona para este drop: no hay ningún cable '
                    . 'cerca de su punto medio (radio ' . ZonaResolverService::RADIO_METROS_DEFAULT . 'm). '
                    . 'Da de alta un cable/NAP cerca primero o acércate a uno existente.',
            ], 422);
        }

        $nombre = app(NomenclaturaService::class)->siguienteDrop($zonaInfo['zona']);

        $layer = MapaRedLayer::create([
            'project_id' => $zonaInfo['proyecto_id'],
            'classification' => 'project',
            'type' => 'polyline',
            'route' => 'drops',
            'dialog' => 'drop',
            'text' => $nombre,
            'label' => 'name',
            'icon' => 'mdi-vector-line',
            'color' => '#2e7d32',
            'weight' => 2,
            'coords' => array_map(
                fn ($p) => ['lat' => (float) $p['lat'], 'lng' => (float) $p['lng']],
                $puntos
            ),
            'data' => ['name' => $nombre, 'description' => null],
        ]);

        return response()->json([
            'layer' => $layer->fresh(),
            'nombre_generado' => $nombre,
            'zona' => $zonaInfo['zona'],
            'proyecto_id' => $zonaInfo['proyecto_id'],
        ], 201);
    }
}
