<?php

namespace App\Modules\Addons\Roadmap\Support;

/**
 * MR-36 (#9990332) — LA REGLA de cuándo una dependencia está cerrada.
 *
 * Aislada y sin dependencias para que su candado corra en PHPUnit PURO: en este proyecto
 * `Tests\TestCase` hace `migrate:fresh --seed` contra la MISMA base de dev (caveat de CLAUDE.md),
 * así que una regla de despacho no puede probarse a través de él.
 *
 * «CERRADA» = `completado` **Y** con el código en `main` (`merge_commit` no nulo).
 *
 * Que esté `completado` NO basta, y el caso que lo prueba es de esta misma mañana: MR-03 (#9990081)
 * estuvo `completado` con su módulo entero fuera de `main` hasta que alguien apretó el merge. Un
 * dependiente que hubiera arrancado con esa señal se habría encontrado el mismo vacío que MR-05.
 */
final class DependenciaItems
{
    /**
     * DECODIFICADOR ÚNICO de `depende_de`. Lo usan el guard del despachador y el texto que se
     * pinta en la Torre, para que no puedan discrepar.
     *
     * Discreparon: el 2026-09-07 el item #9990418 tenía el valor DOBLEMENTE codificado
     * (`'"[9990411,9990417]"'` — un string JSON que contiene otro string JSON, por pasar un
     * `json_encode()` ya hecho a un campo con cast `array`). El guard decodificaba una segunda vez
     * y acertaba; el texto de la Torre decodificaba una sola y obtenía un string, que al pasar por
     * `(array)` + `intval` se convertía en `[]` → **decía «sin dependencias abiertas» sobre un item
     * que sí las tenía**. Un freno correcto con una explicación falsa es peor que un freno a secas:
     * manda a diagnosticar al lugar equivocado.
     *
     * Tolera: array ya decodificado, JSON normal, JSON doble-codificado y basura (devuelve []).
     *
     * @return array<int> ids limpios, sin duplicados
     */
    public static function ids(mixed $raw): array
    {
        // Hasta dos vueltas de decodificación: más sería aceptar cualquier cosa.
        for ($i = 0; $i < 2 && is_string($raw); $i++) {
            $raw = json_decode($raw, true);
        }

        // Exige una LISTA, no cualquier array: un objeto JSON (`{"a":1}`) decodifica a array
        // asociativo, y mapear `intval` sobre sus VALORES devolvería ids inventados a partir de
        // datos que no son una lista de dependencias. Lo cazó su propio test.
        if (! is_array($raw) || ! array_is_list($raw)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $raw))));
    }

    /**
     * ¿Se puede despachar un item con estas dependencias?
     *
     * @param  array|null  $dependeDe  ids declarados (null/[] = sin dependencias)
     * @param  array       $cerrados   mapa id => bool de cuáles están cerrados
     * @return array{puede:bool, faltan:array}  `faltan` = ids que aún no cierran (para el aviso)
     */
    public static function evaluar(?array $dependeDe, array $cerrados): array
    {
        $ids = array_values(array_filter(array_map('intval', (array) $dependeDe)));
        if ($ids === []) {
            return ['puede' => true, 'faltan' => []];
        }

        $faltan = [];
        foreach ($ids as $id) {
            // FALLA-SEGURA: un id que no aparece en el mapa (item borrado, dato corrupto) cuenta
            // como NO cerrado. Ante la duda no se despacha — al revés sería abrir la puerta justo
            // cuando el dato es poco fiable.
            if (empty($cerrados[$id])) {
                $faltan[] = $id;
            }
        }

        return ['puede' => $faltan === [], 'faltan' => $faltan];
    }

    /**
     * ¿El grafo tiene un ciclo alcanzable desde `$id`? Un ciclo deja items que NUNCA se despachan
     * y nadie sabe por qué: es peor que un freno, porque no se ve.
     *
     * @param  array  $grafo  id => array de ids de los que depende
     */
    public static function tieneCiclo(int $id, array $grafo, array $visitados = []): bool
    {
        if (isset($visitados[$id])) {
            return true;
        }
        $visitados[$id] = true;

        foreach ((array) ($grafo[$id] ?? []) as $dep) {
            if (self::tieneCiclo((int) $dep, $grafo, $visitados)) {
                return true;
            }
        }

        return false;
    }
}
