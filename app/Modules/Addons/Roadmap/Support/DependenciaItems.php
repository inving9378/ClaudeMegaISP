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
