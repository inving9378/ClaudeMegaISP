<?php

namespace App\Modules\Addons\MapaRed\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MapaRed\Models\MapaRedDevice;
use App\Modules\Addons\MapaRed\Models\MapaRedLayer;
use App\Modules\Addons\MapaRed\Models\MapaRedTipoSplitter;
use App\Modules\Addons\MapaRed\Services\NomenclaturaService;
use App\Modules\Addons\MapaRed\Services\SnapService;
use App\Modules\Addons\MapaRed\Services\ZonaResolverService;
use Illuminate\Http\Request;

/**
 * MR-24d (item roadmap #9990437, recreado en #9990546 porque la rama original nunca se
 * mergeó a main y quedó obsoleta) — alta rápida de NAP (tipo + splitter, sin nombre a mano).
 * Encadena en un solo POST lo que MR-24b/c/D24 ya resuelven por separado:
 *   1) zona/proyecto por el punto (ZonaResolverService::resolverPorPunto, D23) — 422 si no hay
 *      ningún cable cerca (sin zona resoluble no hay a qué proyecto/nombre atarla).
 *   2) nombre siguiente (NomenclaturaService::siguienteNap, D24).
 *   3) snap al troncal más cercano <=15m (SnapService::cableMasCercano, MR-24c) — solo
 *      informativo, ver decisión abajo.
 *   4) crea la NAP (MapaRedLayer dialog='service_box', mismo shape que envía
 *      BoxServiceComponent.vue vía LayersController::store; dispara createCharolas() solo por
 *      el boot() del modelo).
 *   5) si viene tipo_splitter_id, cuelga un splitter (MapaRedDevice type='splitter' + su
 *      createPorts(), el mismo patrón que ya usa DevicesController::store — NO se toca
 *      MapaRedSplitter/MapaRedTipoSplitter de MR-13, que no tiene controller/ruta propia).
 *
 * DECISIÓN (heredada de #9990437): el troncal que encuentra el snap se devuelve en la respuesta
 * (troncal_asociado / aviso) pero NO se persiste ningún vínculo NAP↔cable — hoy no existe
 * columna ni pivot para esa relación sobre MapaRedCable (el pivot legacy MapaRedLayerRoute
 * apunta a MapaRedLayer dialog='route', un modelo distinto; ver nota de clase en SnapService).
 * mapared_cables sigue vacía en dev, así que en la práctica no hay match — no bloquea el alta,
 * que es el requisito explícito del spec. Diseñar esa persistencia queda para cuando MR-09
 * tenga un flujo real que pueble mapared_cables desde el mapa.
 */
class NapAltaRapidaController extends Controller
{
    public function __construct(private ZonaResolverService $zonaResolver)
    {
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'tipo_splitter_id' => 'nullable|integer|exists:mapared_tipo_splitter,id',
        ]);

        $lat = (float) $data['lat'];
        $lng = (float) $data['lng'];

        $zonaInfo = $this->zonaResolver->resolverPorPunto($lat, $lng);
        if (!$zonaInfo || !$zonaInfo['zona']) {
            return response()->json([
                'message' => 'No se pudo resolver una zona para este punto: no hay ningún cable '
                    . 'cerca (radio ' . ZonaResolverService::RADIO_METROS_DEFAULT . 'm). Da de alta '
                    . 'un cable/troncal primero o acércate a uno existente.',
            ], 422);
        }

        $nombre = app(NomenclaturaService::class)->siguienteNap($zonaInfo['zona']);
        $troncal = SnapService::cableMasCercano($lat, $lng);

        $layer = MapaRedLayer::create([
            'project_id' => $zonaInfo['proyecto_id'],
            'classification' => 'project',
            'type' => 'marker',
            'route' => 'serviceboxs',
            'dialog' => 'service_box',
            'text' => $nombre,
            'icon' => 'mdi-package',
            'icon_color' => '#FFFFFF',
            'color' => '#5bc0de',
            'label' => 'name',
            'coords' => ['lat' => $lat, 'lng' => $lng],
            'data' => ['name' => $nombre, 'description' => null],
        ]);

        $splitter = null;
        if (!empty($data['tipo_splitter_id'])) {
            $tipoSplitter = MapaRedTipoSplitter::find($data['tipo_splitter_id']);

            $device = MapaRedDevice::create([
                'name' => $tipoSplitter->nombre,
                'type' => 'splitter',
                'layer_id' => $layer->id,
                'data' => ['ports' => $tipoSplitter->numero_puertos],
            ]);
            $device->createPorts();

            $splitter = [
                'device_id' => $device->id,
                'tipo_splitter_id' => $tipoSplitter->id,
                'ratio' => $tipoSplitter->ratio,
                'numero_puertos' => $tipoSplitter->numero_puertos,
            ];
        }

        return response()->json([
            'layer' => $layer->fresh(),
            'nombre_generado' => $nombre,
            'zona' => $zonaInfo['zona'],
            'proyecto_id' => $zonaInfo['proyecto_id'],
            'troncal_asociado' => $troncal,
            'aviso' => $troncal ? null : 'Sin troncal cercano (<=15m); la NAP se creó igual.',
            'splitter' => $splitter,
        ], 201);
    }
}
