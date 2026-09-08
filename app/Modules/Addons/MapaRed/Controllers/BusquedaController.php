<?php

namespace App\Modules\Addons\MapaRed\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\MapaRed\Models\MapaRedDevice;
use App\Modules\Addons\MapaRed\Models\MapaRedEnlaceServicio;
use Illuminate\Http\Request;

/**
 * MR-22 Fase 1a-ii (item roadmap #9990533) — buscador global del mapa: nodos por nombre y
 * clientes/ONTs por el enlace de servicio (MR-14). Gateado por el mismo permiso `mapa_red_view`
 * que el resto de `/mapa-red/api/**` (patrón de NapOcupacionController/CoberturaController).
 *
 * Eje "Dirección" (4to del spec original #9990509): NO implementado. `mapared_devices.description`
 * es el único candidato como proxy, pero está vacío en el 100% de los dispositivos reales de dev
 * (verificado: 0 de N con description no nulo) — no es un proxy útil hoy. Decisión reversible
 * registrada con circuito:reportar --tipo=decision: se deja ese eje fuera hasta que exista un
 * campo de dirección real.
 */
class BusquedaController extends Controller
{
    private const LIMITE_POR_GRUPO = 10;
    private const MIN_CHARS = 3;

    public function buscar(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < self::MIN_CHARS) {
            return response()->json(['nodos' => [], 'clientes' => [], 'onts' => []]);
        }

        return response()->json([
            'nodos' => $this->buscarNodos($q),
            'clientes' => $this->buscarEnlaces($q, 'cliente_nombre'),
            'onts' => $this->buscarEnlaces($q, 'ont_serie'),
        ]);
    }

    private function buscarNodos(string $q): array
    {
        return MapaRedDevice::query()
            ->where('name', 'like', "%{$q}%")
            ->orderBy('name')
            ->limit(self::LIMITE_POR_GRUPO)
            ->get(['id', 'type', 'name', 'lat', 'lng'])
            ->map(fn (MapaRedDevice $d) => [
                'id' => $d->id,
                'tipo' => $d->type,
                'label' => $d->name,
                'lat' => $d->lat !== null ? (float) $d->lat : null,
                'lng' => $d->lng !== null ? (float) $d->lng : null,
            ])
            ->all();
    }

    /**
     * Un enlace de servicio no trae lat/lng propio (MR-14): se resuelve subiendo
     * enlace -> puerto_nap_id -> mapared_puertos -> puertable (MorphTo) -> lat/lng del dueño.
     * Si el dueño del puerto no tiene columnas lat/lng (ej. un splitter sin coords propias),
     * el acceso al atributo devuelve null en vez de tronar (magic getter de Eloquent).
     */
    private function buscarEnlaces(string $q, string $columna): array
    {
        return MapaRedEnlaceServicio::query()
            ->where($columna, 'like', "%{$q}%")
            ->orderBy($columna)
            ->limit(self::LIMITE_POR_GRUPO)
            ->with('puertoNap.puertable')
            ->get()
            ->map(function (MapaRedEnlaceServicio $enlace) {
                $duenoPuerto = $enlace->puertoNap?->puertable;

                return [
                    'id' => $enlace->id,
                    'cliente_nombre' => $enlace->cliente_nombre,
                    'ont_serie' => $enlace->ont_serie,
                    'estado' => $enlace->estado,
                    'lat' => $duenoPuerto?->lat !== null ? (float) $duenoPuerto->lat : null,
                    'lng' => $duenoPuerto?->lng !== null ? (float) $duenoPuerto->lng : null,
                ];
            })
            ->all();
    }
}
