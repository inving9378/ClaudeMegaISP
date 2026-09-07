<?php

namespace App\Modules\Addons\MapaRed\Services;

use App\Modules\Addons\MapaRed\Models\MapaRedCable;
use App\Modules\Addons\MapaRed\Models\MapaRedDevice;
use App\Modules\Addons\MapaRed\Models\MapaRedEmpalme;
use App\Modules\Addons\MapaRed\Models\MapaRedEnlaceServicio;
use App\Modules\Addons\MapaRed\Models\MapaRedHilo;
use App\Modules\Addons\MapaRed\Models\MapaRedLayer;
use App\Modules\Addons\MapaRed\Models\MapaRedPuerto;
use App\Modules\Addons\MapaRed\Models\MapaRedSplitter;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * MR-16 Fase 1 (item roadmap #9990468) — grafo genérico de la red física, extraído de
 * `OpticalBudgetService::trazarRuta()` (MR-18/#954), que seguía siendo un caminador interno
 * ad-hoc. El algoritmo de caminado NO cambió (mismo comentario histórico: tolera ciclos y
 * rutas incompletas) — esto es solo la extracción a un servicio de primera clase, genérico y
 * cacheado, para que cualquier consumidor futuro (dibujo en el mapa, otros presupuestos, etc.)
 * lo reuse sin duplicar el caminador.
 */
class RedGraphService
{
    private const CACHE_PREFIX = 'mapared:red_graph';

    private const CACHE_VERSION_KEY = self::CACHE_PREFIX.':version';

    /** TTL largo (spec MR-16 Fase 1: "TTL largo o forever") — se auto-purga solo, no se acumula para siempre. */
    private const CACHE_TTL_DIAS = 30;

    /**
     * Camina desde el enlace de servicio hacia la OLT, sumando segmentos. Cacheado por
     * `enlace_id` (ver `invalidarCache()` para la invalidación explícita al editar empalmes).
     *
     * Tolerante a rutas incompletas (reporta hasta dónde llegó y por qué) y a ciclos (corta y
     * lo marca, nunca se cuelga) — mismo espíritu de robustez que pedía el DoD original de MR-16.
     *
     * Contrato INTACTO respecto al `trazarRuta()` que reemplaza: mismas 5 llaves de retorno
     * (completa/motivo_corte/segmentos/puerto_pon/longitud_total_metros).
     */
    public function trazar(MapaRedEnlaceServicio $enlace, int $maxSaltos = 64): array
    {
        return $this->recordar(
            $this->claveEnlace((int) $enlace->id),
            function () use ($enlace, $maxSaltos) {
                $ruta = $this->caminar($enlace, $maxSaltos);

                if ($ruta['puerto_pon']) {
                    $this->indexarEnlaceEnPuertoPon((int) $ruta['puerto_pon']->id, (int) $enlace->id);
                }

                return $ruta;
            }
        );
    }

    /**
     * Segundo punto de entrada (spec MR-16 Fase 1, punto 1): "arrancar" desde un puerto PON en
     * vez de un enlace de servicio.
     *
     * IMPORTANTE — por diseño el caminador SOLO camina en una dirección: del cliente (enlace de
     * servicio) HACIA ARRIBA, hasta el puerto PON. Un puerto PON es compartido por muchos
     * clientes (fan-out de splitters en cascada, MR-13) y NO tiene un único "hacia abajo": no
     * existe una ruta lineal que caminar en reversa desde el puerto hasta UN cliente en
     * particular — sería un árbol con muchas ramas, no un trazo. Reconstruirlo requeriría un
     * caminador de bajada distinto (fan-out completo), fuera de alcance de esta Fase 1 y del
     * DoD ("NO tocar el algoritmo de caminado en sí").
     *
     * Por eso este método NO devuelve un trazo: devuelve los `enlace_id` que YA se sabe que
     * llegan a este puerto PON (indexados como efecto secundario de `trazar()`, vía el cache
     * de esta misma clase). Es lo que "se necesita para identificar el trazo" pedido por el
     * spec — el llamador toma uno de estos ids y pide su trazo individual con `trazar()`.
     * Best-effort: solo conoce los enlaces que ya se recorrieron al menos una vez desde que la
     * versión de caché actual está vigente; no es un índice completo de todos los clientes del
     * puerto.
     */
    public function trazarDesdePuertoPon(MapaRedPuerto $puertoPon): array
    {
        if ($puertoPon->rol !== MapaRedPuerto::ROL_PON) {
            throw new InvalidArgumentException('El puerto no es un puerto PON (mapared_puertos.rol debe ser "'.MapaRedPuerto::ROL_PON.'").');
        }

        return Cache::get($this->clavePuerto((int) $puertoPon->id), []);
    }

    /**
     * Mismo trazo de `trazar()`, reformateado como lista ORDENADA de elementos consumible por
     * el frontend para dibujar (spec MR-16 Fase 1, punto 3): tipo + id + coordenadas cuando el
     * modelo subyacente ya las tiene (`mapared_devices`/`mapared_cables` traen `lat`/`lng` desde
     * MR-04, #940; splitters/empalmes/puertos NO tienen columna propia — su posición es la de su
     * device/contenedor). Sin coordenada conocida, `posicion` viaja `null` (el frontend decide
     * qué hacer, no se inventa nada).
     */
    public function paraDibujo(MapaRedEnlaceServicio $enlace, int $maxSaltos = 64): array
    {
        return $this->recordar(
            $this->claveEnlace((int) $enlace->id).':dibujo',
            function () use ($enlace, $maxSaltos) {
                $ruta = $this->trazar($enlace, $maxSaltos);

                $puertoInicial = $enlace->puerto_nap_id ? MapaRedPuerto::find($enlace->puerto_nap_id) : null;

                $elementos = [[
                    'tipo' => 'enlace_servicio',
                    'id' => (int) $enlace->id,
                    'etiqueta' => $enlace->cliente_nombre,
                    'posicion' => $puertoInicial ? $this->posicionDeviceDe($puertoInicial->puertable) : null,
                ]];

                foreach ($ruta['segmentos'] as $segmento) {
                    $elementos[] = match ($segmento['tipo']) {
                        'cable' => $this->elementoCable($segmento),
                        'empalme' => $this->elementoEmpalme($segmento),
                        'splitter' => $this->elementoSplitter($segmento),
                        default => ['tipo' => $segmento['tipo'], 'id' => null, 'posicion' => null],
                    };
                }

                if ($ruta['puerto_pon']) {
                    $elementos[] = [
                        'tipo' => 'puerto_pon',
                        'id' => (int) $ruta['puerto_pon']->id,
                        'etiqueta' => trim(($ruta['puerto_pon']->frame ?? '').'/'.($ruta['puerto_pon']->slot ?? '').'/'.($ruta['puerto_pon']->numero ?? ''), '/'),
                        'posicion' => $this->posicionDeviceDe($ruta['puerto_pon']->puertable),
                    ];
                }

                return [
                    'completa' => $ruta['completa'],
                    'motivo_corte' => $ruta['motivo_corte'],
                    'longitud_total_metros' => $ruta['longitud_total_metros'],
                    'elementos' => $elementos,
                ];
            }
        );
    }

    /**
     * MR-17 Fase 1 (item roadmap #9990552) — motor de trazo AGUAS ABAJO (fan-out): dado
     * cualquier elemento de la red, devuelve TODOS los `mapared_enlaces_servicio` alcanzables
     * hacia el cliente. A diferencia de `trazar()`/`trazarDesdePuertoPon()` (que solo conocen lo
     * YA caminado hacia arriba desde un enlace concreto, best-effort), esto es un recorrido
     * recursivo COMPLETO en tiempo real (decisión ya tomada por Irving en #953 q1: sin tabla
     * materializada) — por eso NO se cachea, a propósito.
     *
     * El único punto real de ramificación es el splitter (1 entrada -> N salidas); todo lo demás
     * es una continuación 1:1 sobre la MISMA relación no dirigida que usa `caminar()` (el
     * empalme de un hilo), solo que aquí se sigue "hacia el lado que no es por donde llegamos"
     * en vez de "hacia el puerto PON".
     *
     * Tipos de `$tipo` soportados: `cable` (MapaRedCable — expande a todos sus hilos: cortar un
     * feeder afecta a todas sus fibras), `hilo` (MapaRedHilo), `puerto` (MapaRedPuerto),
     * `splitter` (MapaRedSplitter — expande a su(s) puerto(s) de entrada) y `nap`/`mufa`
     * (MapaRedLayer — expande a sus puertos propios, típicamente `nap_salida`).
     *
     * @return array{enlace_ids: int[], advertencias: string[]} `advertencias` documenta ciclos o
     *         límites de profundidad alcanzados (diagnóstico, no bloquea: el resto de ramas sigue).
     */
    public function fanOutDesde(string $tipo, int $id, int $maxSaltos = 64): array
    {
        $visitados = [];
        $enlaceIds = [];
        $advertencias = [];

        foreach ($this->nodosInicialesFanOut($tipo, $id) as $nodo) {
            $this->recorrerAguasAbajo($nodo, $visitados, $enlaceIds, $advertencias, $maxSaltos, 0);
        }

        $enlaceIds = array_values(array_unique($enlaceIds));
        sort($enlaceIds);

        return [
            'enlace_ids' => $enlaceIds,
            'advertencias' => $advertencias,
        ];
    }

    /**
     * Invalidación EXPLÍCITA (spec MR-16 Fase 1, punto 2). Bumpea la "versión" del namespace de
     * caché en vez de andar borrando llaves una por una: cualquier clave calculada DESPUÉS de
     * este bump incluye la nueva versión, así que las lecturas siguientes recalculan solas
     * (las entradas viejas quedan huérfanas y expiran solas por el TTL, no hay que limpiarlas).
     * Registrada como listener de `MapaRedEmpalme` en `ModuleServiceProvider::boot()` — NO hay
     * hoy ningún controller que mute empalmes (grep en toda la app: cero resultados fuera del
     * modelo y de este servicio), así que enganchar al modelo es lo único que cubre a
     * CUALQUIER futuro punto de escritura sin tener que acordarse de llamarlo a mano.
     */
    public static function invalidarCache(): void
    {
        Cache::forever(self::CACHE_VERSION_KEY, (string) now()->getPreciseTimestamp(3));
    }

    /**
     * Caminador interno: cuerpo idéntico al `trazarRuta()` original de `OpticalBudgetService`
     * (solo movido de archivo). Privado a propósito — el único punto de entrada público es
     * `trazar()` (que además cachea e indexa).
     */
    private function caminar(MapaRedEnlaceServicio $enlace, int $maxSaltos): array
    {
        $segmentos = [];
        $visitados = [];
        $completa = false;
        $motivoCorte = null;
        $puertoPon = null;

        $nodo = $this->nodoInicial($enlace);

        $saltos = 0;
        while ($nodo !== null) {
            if ($saltos++ >= $maxSaltos) {
                $motivoCorte = 'max_saltos_excedido';
                break;
            }

            $clave = $nodo['tipo'].':'.$nodo['id'];
            if (isset($visitados[$clave])) {
                $motivoCorte = 'ciclo_detectado';
                break;
            }
            $visitados[$clave] = true;

            if ($nodo['tipo'] === 'puerto') {
                $puerto = MapaRedPuerto::find($nodo['id']);
                if (! $puerto) {
                    $motivoCorte = 'puerto_inexistente';
                    break;
                }

                if ($puerto->rol === MapaRedPuerto::ROL_PON) {
                    $puertoPon = $puerto;
                    $completa = true;
                    break;
                }

                if ($puerto->puertable_type === MapaRedSplitter::class) {
                    $splitter = MapaRedSplitter::find($puerto->puertable_id);
                    if (! $splitter) {
                        $motivoCorte = 'splitter_inexistente';
                        break;
                    }

                    $segmentos[] = [
                        'tipo' => 'splitter',
                        'splitter_id' => $splitter->id,
                        'nivel' => $splitter->nivel,
                        'perdida_db' => $splitter->getPerdidaEfectivaDbAttribute(),
                    ];

                    $nodo = $this->siguienteTrasSplitter($splitter);
                    if ($nodo === null) {
                        $motivoCorte = 'splitter_sin_entrada_resuelta';
                    }

                    continue;
                }

                // Puerto pasivo sin pérdida modelada (NAP/ODF/splitter integrado sin fila
                // propia en mapared_splitters) — passthrough, seguir buscando el empalme
                // que lo alimenta.
                $nodo = $this->nodoDesdeEmpalmeQueApunta($puerto);
                if ($nodo === null) {
                    $motivoCorte = 'sin_empalme_hacia_el_puerto';
                }

                continue;
            }

            // $nodo['tipo'] === 'hilo'
            $hilo = MapaRedHilo::with('cable')->find($nodo['id']);
            if (! $hilo) {
                $motivoCorte = 'hilo_inexistente';
                break;
            }

            // Cada hilo visitado (el inicial del enlace y cualquier otro alcanzado aguas
            // arriba: feeder, distribución) aporta su propio tramo de cable.
            if ($hilo->cable) {
                $segmentos[] = [
                    'tipo' => 'cable',
                    'cable_id' => $hilo->cable_id,
                    'hilo_id' => $hilo->id,
                    'metros' => (float) $hilo->cable->longitud_metros,
                ];
            }

            // `mapared_empalmes::hiloDisponible()` (MR-12/#948) limita CADA hilo a un único
            // empalme activo, como hilo_a o como extremo_b. Si llegamos a este hilo
            // atravesando justo ese empalme, hay que EXCLUIRLO de la búsqueda: si no, la única
            // fila que existe es la misma por la que acabamos de entrar y el trazo rebotaría
            // hacia atrás (se leería como ciclo sin serlo). Sin más filas que esa, es un corte
            // real: el otro extremo del hilo no tiene más splices registrados.
            $empalme = $this->empalmeDelHilo($hilo->id, $nodo['via_empalme_id'] ?? null);
            if (! $empalme) {
                $motivoCorte = 'sin_empalme_saliente';
                break;
            }

            $esHiloA = (int) $empalme->hilo_a_id === (int) $hilo->id;
            $extremo = $esHiloA
                ? ['tipo' => $empalme->extremo_b_type, 'id' => $empalme->extremo_b_id]
                : ['tipo' => MapaRedHilo::class, 'id' => $empalme->hilo_a_id];

            $segmentos[] = [
                'tipo' => 'empalme',
                'empalme_id' => $empalme->id,
                'clase' => $empalme->tipo,
                'perdida_db' => (float) ($empalme->perdida_db ?? (MapaRedEmpalme::PERDIDA_DB_DEFAULT[$empalme->tipo] ?? 0)),
            ];

            $nodo = $extremo['tipo'] === MapaRedHilo::class
                ? $this->siguienteHilo((int) $extremo['id'], $empalme->id)
                : $this->siguientePuerto((int) $extremo['id']);
        }

        return [
            'completa' => $completa,
            'motivo_corte' => $motivoCorte,
            'segmentos' => $segmentos,
            'puerto_pon' => $puertoPon,
            'longitud_total_metros' => array_sum(array_column(
                array_filter($segmentos, fn ($s) => $s['tipo'] === 'cable'),
                'metros'
            )),
        ];
    }

    /**
     * Nodo de arranque del trazo. No suma segmentos aquí: el hilo inicial se procesa como
     * cualquier otro nodo 'hilo' dentro del bucle de `caminar()`, que es quien le suma su
     * propio tramo de cable.
     */
    private function nodoInicial(MapaRedEnlaceServicio $enlace): ?array
    {
        if ($enlace->hilo_id && MapaRedHilo::whereKey($enlace->hilo_id)->exists()) {
            return $this->siguienteHilo($enlace->hilo_id);
        }

        if ($enlace->puerto_nap_id) {
            return ['tipo' => 'puerto', 'id' => $enlace->puerto_nap_id];
        }

        return null;
    }

    private function siguienteHilo(int $hiloId, ?int $viaEmpalmeId = null): array
    {
        return ['tipo' => 'hilo', 'id' => $hiloId, 'via_empalme_id' => $viaEmpalmeId];
    }

    private function siguientePuerto(int $puertoId): array
    {
        return ['tipo' => 'puerto', 'id' => $puertoId];
    }

    /**
     * Empalme activo en el que participa el hilo, sea como `hilo_a_id` o como extremo B.
     * `$excluirId` descarta el empalme por el que ya se llegó a este hilo.
     */
    private function empalmeDelHilo(int $hiloId, ?int $excluirId = null): ?MapaRedEmpalme
    {
        return MapaRedEmpalme::query()
            ->where(function ($q) use ($hiloId) {
                $q->where('hilo_a_id', $hiloId)
                    ->orWhere(function ($q2) use ($hiloId) {
                        $q2->where('extremo_b_type', MapaRedHilo::class)->where('extremo_b_id', $hiloId);
                    });
            })
            ->when($excluirId, fn ($q) => $q->where('id', '!=', $excluirId))
            ->first();
    }

    /**
     * Empalme activo cuyo extremo B es este puerto (lo que lo alimenta desde aguas abajo del
     * cliente, es decir, aguas arriba en el sentido del trazo).
     */
    private function nodoDesdeEmpalmeQueApunta(MapaRedPuerto $puerto): ?array
    {
        $empalme = MapaRedEmpalme::query()
            ->where('extremo_b_type', MapaRedPuerto::class)
            ->where('extremo_b_id', $puerto->id)
            ->first();

        if (! $empalme) {
            return null;
        }

        return $this->siguienteHilo((int) $empalme->hilo_a_id, $empalme->id);
    }

    /**
     * Tras sumar la pérdida de un splitter, sigue la cascada: si es nivel 2, sube directo al
     * puerto de salida del padre (nivel 1) vía `puerto_entrada_padre_id`; si es nivel 1, busca
     * qué alimenta su propio puerto de entrada.
     */
    private function siguienteTrasSplitter(MapaRedSplitter $splitter): ?array
    {
        if ($splitter->nivel === MapaRedSplitter::NIVEL_2 && $splitter->puerto_entrada_padre_id) {
            return ['tipo' => 'puerto', 'id' => $splitter->puerto_entrada_padre_id];
        }

        $puertoEntrada = MapaRedPuerto::query()
            ->delDueno(MapaRedSplitter::class, $splitter->id)
            ->where('rol', MapaRedPuerto::ROL_SPLITTER_IN)
            ->first();

        if (! $puertoEntrada) {
            return null;
        }

        return $this->nodoDesdeEmpalmeQueApunta($puertoEntrada);
    }

    private function elementoCable(array $segmento): array
    {
        $cable = MapaRedCable::find($segmento['cable_id']);

        return [
            'tipo' => 'cable',
            'id' => $segmento['cable_id'],
            'hilo_id' => $segmento['hilo_id'],
            'metros' => $segmento['metros'],
            'posicion' => ($cable && $cable->lat !== null && $cable->lng !== null)
                ? ['lat' => (float) $cable->lat, 'lng' => (float) $cable->lng]
                : null,
        ];
    }

    private function elementoEmpalme(array $segmento): array
    {
        $empalme = MapaRedEmpalme::find($segmento['empalme_id']);

        return [
            'tipo' => 'empalme',
            'id' => $segmento['empalme_id'],
            'clase' => $segmento['clase'],
            'perdida_db' => $segmento['perdida_db'],
            'posicion' => $empalme ? $this->posicionDeviceDe($empalme->elementoContenedor) : null,
        ];
    }

    private function elementoSplitter(array $segmento): array
    {
        $splitter = MapaRedSplitter::find($segmento['splitter_id']);

        return [
            'tipo' => 'splitter',
            'id' => $segmento['splitter_id'],
            'nivel' => $segmento['nivel'],
            'perdida_db' => $segmento['perdida_db'],
            'posicion' => $splitter ? $this->posicionDeviceDe($splitter->device) : null,
        ];
    }

    /**
     * Coordenada geográfica real (`lat`/`lng`, D8/MR-04) del device dueño del elemento, cuando
     * se puede resolver. `mapared_splitters`/`mapared_puertos`/`mapared_empalmes` no tienen
     * columna propia de posición — heredan la de su `MapaRedDevice` contenedor. Sin device o sin
     * lat/lng cargado, `null` (el frontend decide qué hacer, no se inventa una posición).
     */
    private function posicionDeviceDe($model): ?array
    {
        if (! $model instanceof MapaRedDevice) {
            return null;
        }

        if ($model->lat === null || $model->lng === null) {
            return null;
        }

        return ['lat' => (float) $model->lat, 'lng' => (float) $model->lng];
    }

    /**
     * Registra, como efecto secundario best-effort de `trazar()`, que este enlace llega al
     * puerto PON dado — así `trazarDesdePuertoPon()` puede resolver "qué enlaces conozco desde
     * este puerto" sin recorrer todo el sistema. Vive bajo la MISMA versión de caché que el
     * resto (se limpia solo con `invalidarCache()`, no hay que tocarlo aparte).
     */
    private function indexarEnlaceEnPuertoPon(int $puertoPonId, int $enlaceId): void
    {
        $clave = $this->clavePuerto($puertoPonId);
        $conocidos = Cache::get($clave, []);

        if (! in_array($enlaceId, $conocidos, true)) {
            $conocidos[] = $enlaceId;
            Cache::put($clave, $conocidos, $this->ttl());
        }
    }

    /**
     * Resuelve los nodos de ARRANQUE del fan-out según el tipo de elemento de entrada de
     * `fanOutDesde()`. Todos devuelven la misma forma de nodo que usa `recorrerAguasAbajo()`.
     */
    private function nodosInicialesFanOut(string $tipo, int $id): array
    {
        return match ($tipo) {
            'cable' => $this->nodosDesdeCable($id),
            'hilo' => [['tipo' => 'hilo', 'id' => (int) MapaRedHilo::findOrFail($id)->id]],
            'puerto' => [['tipo' => 'puerto', 'id' => (int) MapaRedPuerto::findOrFail($id)->id]],
            'splitter' => $this->nodosDesdeSplitter(MapaRedSplitter::findOrFail($id)),
            'nap', 'mufa' => $this->nodosDesdeNap(MapaRedLayer::findOrFail($id)),
            default => throw new InvalidArgumentException("Tipo de elemento no soportado para fan-out: \"{$tipo}\"."),
        };
    }

    /**
     * Un cable es un haz de hilos: cortarlo afecta a TODOS, cada uno pudiendo ir a un splitter/
     * NAP distinto aguas abajo. Cada hilo es su propia rama de arranque.
     */
    private function nodosDesdeCable(int $cableId): array
    {
        if (! MapaRedCable::whereKey($cableId)->exists()) {
            throw new InvalidArgumentException("El cable #{$cableId} no existe.");
        }

        return MapaRedHilo::query()
            ->where('cable_id', $cableId)
            ->pluck('id')
            ->map(fn ($hiloId) => ['tipo' => 'hilo', 'id' => (int) $hiloId])
            ->all();
    }

    /**
     * Arranca en el/los puerto(s) `splitter_in` del splitter: el recorrido de puerto ya sabe
     * expandir un `splitter_in` a todas sus salidas (fan-out real).
     */
    private function nodosDesdeSplitter(MapaRedSplitter $splitter): array
    {
        return MapaRedPuerto::query()
            ->delDueno(MapaRedSplitter::class, $splitter->id)
            ->where('rol', MapaRedPuerto::ROL_SPLITTER_IN)
            ->pluck('id')
            ->map(fn ($puertoId) => ['tipo' => 'puerto', 'id' => (int) $puertoId])
            ->all();
    }

    /**
     * Arranca en los puertos propios de la NAP/mufa (típicamente `nap_salida` — cubre el caso
     * trivial de NAP final, hoja directa al cliente).
     *
     * LIMITACIÓN CONOCIDA (mismo hallazgo ya documentado en `EmpalmesController`): un splitter
     * "adentro" de la mufa/NAP no tiene puertos con `puertable_type=MapaRedLayer` (sus puertos
     * son propios, `puertable_type=MapaRedSplitter`) — no existe relación directa Layer->Splitter.
     * Se resuelve igual el caso real vía `MapaRedDevice.layer_id` -> `MapaRedSplitter.device_id`,
     * que es la única relación que sí existe entre una NAP y los splitters que contiene.
     */
    private function nodosDesdeNap(MapaRedLayer $nap): array
    {
        $nodos = MapaRedPuerto::query()
            ->delDueno(MapaRedLayer::class, $nap->id)
            ->pluck('id')
            ->map(fn ($puertoId) => ['tipo' => 'puerto', 'id' => (int) $puertoId])
            ->all();

        $splitters = MapaRedSplitter::query()
            ->whereIn('device_id', MapaRedDevice::query()->where('layer_id', $nap->id)->pluck('id'))
            ->get();

        foreach ($splitters as $splitter) {
            $nodos = array_merge($nodos, $this->nodosDesdeSplitter($splitter));
        }

        return $nodos;
    }

    /**
     * Recorrido recursivo aguas abajo, un nodo a la vez. `$visitados` es un set global
     * compartido por TODAS las ramas de una misma llamada a `fanOutDesde()` (protección de
     * ciclos); `$profundidad` corta por `$maxSaltos` igual que `caminar()` corta por saltos.
     * Ninguno de los dos corte detiene las DEMÁS ramas: solo queda anotado en `$advertencias`.
     */
    private function recorrerAguasAbajo(
        array $nodo,
        array &$visitados,
        array &$enlaceIds,
        array &$advertencias,
        int $maxSaltos,
        int $profundidad
    ): void {
        if ($profundidad >= $maxSaltos) {
            $advertencias[] = "límite de profundidad alcanzado en {$nodo['tipo']}:{$nodo['id']}";

            return;
        }

        $clave = $nodo['tipo'].':'.$nodo['id'];
        if (isset($visitados[$clave])) {
            $advertencias[] = "ciclo detectado en {$clave}";

            return;
        }
        $visitados[$clave] = true;

        if ($nodo['tipo'] === 'puerto') {
            $this->recorrerPuertoAguasAbajo($nodo, $visitados, $enlaceIds, $advertencias, $maxSaltos, $profundidad);

            return;
        }

        $this->recorrerHiloAguasAbajo($nodo, $visitados, $enlaceIds, $advertencias, $maxSaltos, $profundidad);
    }

    /**
     * Un hilo participa en A LO MÁS un empalme activo (`MapaRedEmpalme::hiloDisponible()`), sea
     * como `hilo_a_id` o como extremo B — por eso basta con excluir el empalme por el que se
     * llegó (`excluir_empalme_id`) para no rebotar hacia la rama de origen.
     */
    private function recorrerHiloAguasAbajo(
        array $nodo,
        array &$visitados,
        array &$enlaceIds,
        array &$advertencias,
        int $maxSaltos,
        int $profundidad
    ): void {
        $hiloId = (int) $nodo['id'];

        foreach (MapaRedEnlaceServicio::query()->where('hilo_id', $hiloId)->pluck('id') as $enlaceId) {
            $enlaceIds[] = (int) $enlaceId;
        }

        $empalme = $this->empalmeDelHilo($hiloId, $nodo['excluir_empalme_id'] ?? null);
        if (! $empalme) {
            return; // fin de esta rama: sin más splices, cable terminal.
        }

        $esHiloA = (int) $empalme->hilo_a_id === $hiloId;
        $extremo = $esHiloA
            ? ['tipo' => $empalme->extremo_b_type, 'id' => $empalme->extremo_b_id]
            : ['tipo' => MapaRedHilo::class, 'id' => $empalme->hilo_a_id];

        $siguiente = $extremo['tipo'] === MapaRedHilo::class
            ? ['tipo' => 'hilo', 'id' => (int) $extremo['id'], 'excluir_empalme_id' => (int) $empalme->id]
            : ['tipo' => 'puerto', 'id' => (int) $extremo['id']];

        $this->recorrerAguasAbajo($siguiente, $visitados, $enlaceIds, $advertencias, $maxSaltos, $profundidad + 1);
    }

    /**
     * En un puerto `splitter_in` ocurre el fan-out real: 1 entrada -> N salidas del mismo
     * splitter. En cualquier otro rol (`splitter_out`, `nap_salida`, `odf`, `ont`, pasivo) la
     * continuación es 1:1: o bien un hilo de bajada empalmado directo al puerto, o bien —solo
     * para `splitter_out`— un splitter nivel 2 que cascadea directo desde este puerto vía
     * `puerto_entrada_padre_id` (FK directa, sin empalme de por medio, MR-13).
     */
    private function recorrerPuertoAguasAbajo(
        array $nodo,
        array &$visitados,
        array &$enlaceIds,
        array &$advertencias,
        int $maxSaltos,
        int $profundidad
    ): void {
        $puerto = MapaRedPuerto::find($nodo['id']);
        if (! $puerto) {
            $advertencias[] = "puerto #{$nodo['id']} no existe";

            return;
        }

        foreach (MapaRedEnlaceServicio::query()->where('puerto_nap_id', $puerto->id)->pluck('id') as $enlaceId) {
            $enlaceIds[] = (int) $enlaceId;
        }

        if ($puerto->puertable_type === MapaRedSplitter::class && $puerto->rol === MapaRedPuerto::ROL_SPLITTER_IN) {
            $salidas = MapaRedPuerto::query()
                ->delDueno(MapaRedSplitter::class, $puerto->puertable_id)
                ->where('rol', MapaRedPuerto::ROL_SPLITTER_OUT)
                ->pluck('id');

            foreach ($salidas as $salidaId) {
                $this->recorrerAguasAbajo(
                    ['tipo' => 'puerto', 'id' => (int) $salidaId],
                    $visitados,
                    $enlaceIds,
                    $advertencias,
                    $maxSaltos,
                    $profundidad + 1
                );
            }

            return;
        }

        if ($puerto->rol === MapaRedPuerto::ROL_SPLITTER_OUT) {
            $hijoCascada = MapaRedSplitter::query()->where('puerto_entrada_padre_id', $puerto->id)->first();

            if ($hijoCascada) {
                $entradaHijo = MapaRedPuerto::query()
                    ->delDueno(MapaRedSplitter::class, $hijoCascada->id)
                    ->where('rol', MapaRedPuerto::ROL_SPLITTER_IN)
                    ->first();

                if ($entradaHijo) {
                    $this->recorrerAguasAbajo(
                        ['tipo' => 'puerto', 'id' => (int) $entradaHijo->id],
                        $visitados,
                        $enlaceIds,
                        $advertencias,
                        $maxSaltos,
                        $profundidad + 1
                    );
                }
            }
        }

        $empalme = MapaRedEmpalme::query()
            ->where('extremo_b_type', MapaRedPuerto::class)
            ->where('extremo_b_id', $puerto->id)
            ->first();

        if (! $empalme) {
            return; // fin de esta rama: nada empalmado aguas abajo de este puerto.
        }

        $this->recorrerAguasAbajo(
            ['tipo' => 'hilo', 'id' => (int) $empalme->hilo_a_id, 'excluir_empalme_id' => (int) $empalme->id],
            $visitados,
            $enlaceIds,
            $advertencias,
            $maxSaltos,
            $profundidad + 1
        );
    }

    private function recordar(string $clave, callable $callback)
    {
        return Cache::remember($clave, $this->ttl(), $callback);
    }

    private function claveEnlace(int $enlaceId): string
    {
        return self::CACHE_PREFIX.':v'.$this->version().':enlace:'.$enlaceId;
    }

    private function clavePuerto(int $puertoId): string
    {
        return self::CACHE_PREFIX.':v'.$this->version().':puerto:'.$puertoId;
    }

    private function version(): string
    {
        return Cache::rememberForever(self::CACHE_VERSION_KEY, fn () => (string) now()->getPreciseTimestamp(3));
    }

    private function ttl(): \DateTimeInterface
    {
        return now()->addDays(self::CACHE_TTL_DIAS);
    }
}
