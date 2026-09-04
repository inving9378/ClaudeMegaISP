<?php

namespace App\Modules\Addons\Roadmap\Services\Descomposicion;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;

/**
 * PIEZA C (#334, rescatada por #629) — GATE de dependencias entre sub-items de un mismo padre.
 * Enforza la regla que hace SEGURA la ejecución en paralelo de las secciones de un item grande:
 *
 *   una sección (sub-item) es ELEGIBLE  ⟺  TODAS sus posiciones predecesoras (`depende_de`)
 *   están en `estado_aprobacion = 'completado'`.
 *
 * Sin dependencias (raíz) ⇒ elegible. Es la pieza que impide que una sección de FRONTEND se libere
 * antes que el BACKEND del que depende (correría en desorden y rompería el build).
 *
 * Convención de persistencia (heredada de la rama huérfana piezaC-gate-dependencia, jul-11):
 *   - la posición del sub-item vive en la columna `position` (1-based) del propio sub-item,
 *   - sus predecesoras (posiciones de HERMANOS bajo el mismo `origen_item_id`) viven en
 *     `subtasks.descomposicion.depende_de`.
 *
 * ⚠️ TODAVÍA NO CABLEADO al scheduler vivo (`RoadmapCircuitoService::ejecutablesParalelo()` /
 * `scopeDespachable()`) ni a `circuito:sub-item` (que hoy no acepta `--depende-de`). Esta clase es
 * PURA y testeable, sin efectos secundarios — el cableado real (filtro extra en el despacho +
 * opción en `SubItemCommand` para declarar la dependencia al crear) queda para un item aparte, ya
 * que toca la lógica de despacho que comparten TODAS las terminales en vuelo.
 */
class DependenciaGate
{
    /** Llave bajo `subtasks` donde se persistiría la cadena de la descomposición. */
    public const META_KEY = 'descomposicion';

    /** Estado que libera a una predecesora. */
    public const ESTADO_LIBERA = 'completado';

    /**
     * NÚCLEO PURO — elegible ⟺ cada posición predecesora está 'completado'. Sin predecesoras
     * (o todas completadas) ⇒ true. Determinista, sin BD.
     *
     * @param int[]             $dependeDe          posiciones de las que depende esta sección
     * @param array<int,string> $estadoPorPosicion  posición => estado_aprobacion del hermano
     */
    public function elegiblePorEstados(array $dependeDe, array $estadoPorPosicion): bool
    {
        foreach ($dependeDe as $pos) {
            if (($estadoPorPosicion[(int) $pos] ?? null) !== self::ESTADO_LIBERA) {
                return false;
            }
        }

        return true;
    }

    /**
     * PURO — posiciones predecesoras que AÚN bloquean (no 'completado'). Vacío = elegible.
     * Para diagnóstico ("la sección N espera a [M, …]").
     *
     * @param int[]             $dependeDe
     * @param array<int,string> $estadoPorPosicion
     * @return int[]
     */
    public function bloqueadaPor(array $dependeDe, array $estadoPorPosicion): array
    {
        return array_values(array_filter(
            array_map('intval', $dependeDe),
            fn (int $pos) => ($estadoPorPosicion[$pos] ?? null) !== self::ESTADO_LIBERA
        ));
    }

    /**
     * PURO — ¿hay un ciclo alcanzable DESDE $desde siguiendo $edges (adjacencia dirigida
     * posición => [posiciones de las que depende])? DFS con pila de recursión (visitados + en
     * pila) para detectar back-edges. Solo mira lo alcanzable desde $desde: un ciclo en otro
     * componente del grafo no cuenta (para eso sirve pasar el nodo de interés, no analizar todo
     * el grafo).
     *
     * @param array<int,int[]> $edges  posición => [posiciones predecesoras] (p.ej. la nueva
     *                                 sección 5 depende de la 3 ⇒ $edges[5] = [3])
     */
    public function tieneCiclo(int $desde, array $edges): bool
    {
        return $this->caminoCiclo($desde, $edges) !== [];
    }

    /**
     * PURO — camino del ciclo alcanzable desde $desde (p.ej. [3, 5, 3]), o [] si no hay ciclo.
     * Mismo DFS que tieneCiclo(); expuesto aparte para que el llamador arme un mensaje de error
     * con el camino exacto ("la posición 3 dependería circularmente de 3 -> 5 -> 3").
     *
     * @param array<int,int[]> $edges
     * @return int[]
     */
    public function caminoCiclo(int $desde, array $edges): array
    {
        $visitados = [];
        $enPila = [];
        $pila = [];

        $dfs = function (int $nodo) use (&$dfs, &$visitados, &$enPila, &$pila, $edges): array {
            $visitados[$nodo] = true;
            $enPila[$nodo] = true;
            $pila[] = $nodo;

            foreach ($edges[$nodo] ?? [] as $vecino) {
                $vecino = (int) $vecino;

                if (! empty($enPila[$vecino])) {
                    $idx = array_search($vecino, $pila, true);

                    return array_merge(array_slice($pila, $idx), [$vecino]);
                }

                if (empty($visitados[$vecino])) {
                    $encontrado = $dfs($vecino);
                    if ($encontrado !== []) {
                        return $encontrado;
                    }
                }
            }

            array_pop($pila);
            $enPila[$nodo] = false;

            return [];
        };

        return $dfs($desde);
    }

    /** Lee las posiciones predecesoras persistidas en el sub-item (subtasks.descomposicion.depende_de). */
    public function dependeDeDe(RoadmapItem $sub): array
    {
        $meta = (array) (($sub->subtasks ?? [])[self::META_KEY] ?? []);

        return array_values(array_map('intval', (array) ($meta['depende_de'] ?? [])));
    }

    /**
     * DB-facing — ¿es elegible este sub-item contra sus HERMANOS (mismo `origen_item_id`)?
     * Un item que NO es sección hija (sin `origen_item_id`) queda fuera del gate ⇒ elegible.
     */
    public function esElegible(RoadmapItem $sub): bool
    {
        if (! $sub->origen_item_id) {
            return true;
        }
        $dependeDe = $this->dependeDeDe($sub);
        if ($dependeDe === []) {
            return true;
        }

        $estados = RoadmapItem::where('origen_item_id', $sub->origen_item_id)
            ->whereIn('position', $dependeDe)
            ->pluck('estado_aprobacion', 'position')
            ->map(fn ($e) => (string) $e)
            ->all();

        return $this->elegiblePorEstados($dependeDe, $estados);
    }

    /**
     * DB-facing — sub-items ELEGIBLES de un padre AHORA (para el scheduler, cuando se cablee).
     * Read-only. Ordenados por `position`.
     *
     * @return RoadmapItem[]
     */
    public function elegibles(int $origenItemId): array
    {
        $subs = RoadmapItem::where('origen_item_id', $origenItemId)
            ->orderBy('position')
            ->get();

        $estadoPorPos = $subs->pluck('estado_aprobacion', 'position')
            ->map(fn ($e) => (string) $e)
            ->all();

        return $subs
            ->filter(fn (RoadmapItem $s) => $this->elegiblePorEstados($this->dependeDeDe($s), $estadoPorPos))
            ->values()
            ->all();
    }

    /**
     * DB-facing — ids de TODOS los sub-items actualmente BLOQUEADOS por dependencia (#9990274),
     * en 2 queries sin N+1. Para consumo de `RoadmapItem::scopeDespachable()`, que necesita
     * excluir estos ids del pool despachable en una sola pasada.
     *
     * @return int[]
     */
    public function idsBloqueados(): array
    {
        $conDependencia = RoadmapItem::whereNotNull('origen_item_id')
            ->whereNotNull('subtasks')
            ->get(['id', 'origen_item_id', 'subtasks'])
            ->filter(fn (RoadmapItem $item) => $this->dependeDeDe($item) !== []);

        if ($conDependencia->isEmpty()) {
            return [];
        }

        $origenIds = $conDependencia->pluck('origen_item_id')->unique()->values()->all();

        $estadoPorPadre = [];
        RoadmapItem::whereIn('origen_item_id', $origenIds)
            ->get(['id', 'origen_item_id', 'position', 'estado_aprobacion'])
            ->each(function (RoadmapItem $hermano) use (&$estadoPorPadre) {
                $estadoPorPadre[$hermano->origen_item_id][(int) $hermano->position] = (string) $hermano->estado_aprobacion;
            });

        return $conDependencia
            ->reject(fn (RoadmapItem $item) => $this->elegiblePorEstados(
                $this->dependeDeDe($item),
                $estadoPorPadre[$item->origen_item_id] ?? []
            ))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
