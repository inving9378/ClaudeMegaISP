<?php

namespace App\Modules\Addons\MapaRed\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MapaRed\Models\MapaRedLayer;
use App\Modules\Addons\MapaRed\Services\NomenclaturaService;
use App\Modules\Addons\MapaRed\Services\SnapService;
use App\Modules\Addons\MapaRed\Services\ZonaResolverService;
use Illuminate\Http\Request;

/**
 * MR-24e Fase 2 (item roadmap #9990548, sub-item de seguimiento de #9990439) — alta rápida de
 * cable/troncal (polilínea) con nomenclatura automática, análoga a NapAltaRapidaController
 * (MR-24d bis, #9990546) pero para el otro tipo de elemento del mapa: NO existía ningún
 * endpoint mapa-red.api.* que usara NomenclaturaService::siguienteTroncal().
 *
 * Encadena en un solo POST:
 *   1) zona/proyecto por el PUNTO MEDIO de la polilínea (ZonaResolverService::resolverPorPunto,
 *      D23) — 422 si no hay ningún cable cerca. DECISIÓN (registrada vía circuito:reportar): se
 *      usa el punto medio (vértice central de la lista, no el promedio geométrico ni el primer
 *      punto) porque un troncal puede cruzar de borde a borde entre dos zonas; el punto medio
 *      describe mejor "dónde vive" el cable que cualquiera de sus dos extremos. El único
 *      consumidor previo de ZonaResolverService (NapAltaRapidaController) resuelve un solo punto
 *      de clic, sin precedente directo para polilíneas.
 *   2) nombre siguiente (NomenclaturaService::siguienteTroncal, D24) → T-{ZONA}-FO{HILOS}-{N}.
 *   3) snap en cada extremo (inicio y fin) al elemento puntual más cercano
 *      (SnapService::elementoMasCercano — NAP/mufa/site/etc.) o al MapaRedCable más cercano
 *      (SnapService::cableMasCercano), lo que quede más cerca dentro de 15m. DECISIÓN: se
 *      reusan los dos métodos que YA existen en SnapService (uno cubre "elementos", el otro
 *      "cables") en vez de escribir un tercer método nuevo — cubre el pedido del padre ("snap
 *      contra elementos u otros cables existentes") sin duplicar lógica de búsqueda por radio.
 *      Solo informativo: NO se persiste ningún vínculo (mismo criterio que MR-24d — no existe
 *      columna/pivot para esa relación todavía).
 *   4) crea el MapaRedLayer del cable: mismo shape que RouteComponent.vue envía hoy vía
 *      LayersController::store (type='polyline', dialog='route', route='routes', label='name'),
 *      así el resto del módulo (createFibers() en el boot() del modelo, panel lateral, KML,
 *      etc.) lo trata exactamente igual que un troncal creado a mano. `data.fibers_amount` viene
 *      de `numero_hilos` — es el campo que createFibers() lee para generar los buffers de fibra.
 *
 * Este endpoint SOLO lo consume el sub-item hermano de frontend (Fase 3); no toca
 * TroncalDialog.vue/AvaiablesRoutesComponent.vue/RouteComponent.vue (flujo manual intacto).
 *
 * PERMISO (item #9990568, seguimiento de la pregunta q4 de #9990548, decisión de Irving = Opción 1):
 * además del gate general `mapa_red_view` (todo /mapa-red/api/**), este `store()` exige el permiso
 * granular `mapa_red.cable.crear_rapido` en línea (mismo patrón defensa-en-profundidad de
 * OLTsOnuController::store con `onu_add`). Sincronizado a super-administrator + DESARROLLADOR
 * (convención del proyecto); asignarlo a un futuro rol "operadores de red" queda pendiente de que
 * Irving defina/cree ese rol.
 */
class CableAltaRapidaController extends Controller
{
    public function __construct(private ZonaResolverService $zonaResolver)
    {
    }

    public function store(Request $request)
    {
        if (! auth()->user()?->can('mapa_red.cable.crear_rapido')) {
            return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
        }

        $data = $request->validate([
            'puntos' => 'required|array|min:2',
            'puntos.*.lat' => 'required|numeric',
            'puntos.*.lng' => 'required|numeric',
            'numero_hilos' => 'required|integer|min:1',
        ]);

        $puntos = array_values($data['puntos']);
        $numeroHilos = (int) $data['numero_hilos'];

        [$latMedio, $lngMedio] = $this->puntoMedio($puntos);

        $zonaInfo = $this->zonaResolver->resolverPorPunto($latMedio, $lngMedio);
        if (!$zonaInfo || !$zonaInfo['zona']) {
            return response()->json([
                'message' => 'No se pudo resolver una zona para este cable: no hay ningún cable '
                    . 'cerca de su punto medio (radio ' . ZonaResolverService::RADIO_METROS_DEFAULT . 'm). '
                    . 'Da de alta un cable/troncal cerca primero o acércate a uno existente.',
            ], 422);
        }

        $nombre = app(NomenclaturaService::class)->siguienteTroncal($zonaInfo['zona'], $numeroHilos);

        $inicio = $puntos[0];
        $fin = $puntos[count($puntos) - 1];

        $layer = MapaRedLayer::create([
            'project_id' => $zonaInfo['proyecto_id'],
            'classification' => 'project',
            'type' => 'polyline',
            'route' => 'routes',
            'dialog' => 'route',
            'text' => $nombre,
            'label' => 'name',
            'icon' => 'mdi-chart-timeline-variant',
            'color' => '#6666ff',
            'weight' => 3,
            'coords' => array_map(
                fn ($p) => ['lat' => (float) $p['lat'], 'lng' => (float) $p['lng']],
                $puntos
            ),
            'distance' => $this->distanciaTotalMetros($puntos),
            'data' => [
                'name' => $nombre,
                'description' => null,
                'fibers_amount' => $numeroHilos,
            ],
        ]);

        return response()->json([
            'layer' => $layer->fresh(),
            'nombre_generado' => $nombre,
            'zona' => $zonaInfo['zona'],
            'proyecto_id' => $zonaInfo['proyecto_id'],
            'extremos_snap' => [
                'inicio' => $this->snapMasCercano((float) $inicio['lat'], (float) $inicio['lng']),
                'fin' => $this->snapMasCercano((float) $fin['lat'], (float) $fin['lng']),
            ],
        ], 201);
    }

    private function snapMasCercano(float $lat, float $lng): ?array
    {
        $elemento = SnapService::elementoMasCercano($lat, $lng);
        $cable = SnapService::cableMasCercano($lat, $lng);

        if ($elemento && $cable) {
            return $elemento['distancia_metros'] <= $cable['distancia_metros']
                ? ['tipo' => 'elemento', ...$elemento]
                : ['tipo' => 'cable', ...$cable];
        }

        if ($elemento) {
            return ['tipo' => 'elemento', ...$elemento];
        }

        if ($cable) {
            return ['tipo' => 'cable', ...$cable];
        }

        return null;
    }

    /**
     * Vértice central de la lista de puntos (no el promedio geométrico): describe "dónde vive"
     * el cable sin necesitar más dependencias que las que ya usa el resto del módulo.
     */
    private function puntoMedio(array $puntos): array
    {
        $medio = $puntos[(int) floor((count($puntos) - 1) / 2)];

        return [(float) $medio['lat'], (float) $medio['lng']];
    }

    private function distanciaTotalMetros(array $puntos): float
    {
        $total = 0.0;
        for ($i = 1; $i < count($puntos); $i++) {
            $total += $this->haversineMetros(
                (float) $puntos[$i - 1]['lat'],
                (float) $puntos[$i - 1]['lng'],
                (float) $puntos[$i]['lat'],
                (float) $puntos[$i]['lng']
            );
        }

        return round($total, 2);
    }

    private function haversineMetros(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000; // metros

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
