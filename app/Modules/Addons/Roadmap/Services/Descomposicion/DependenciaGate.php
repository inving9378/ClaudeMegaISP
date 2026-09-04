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
}
