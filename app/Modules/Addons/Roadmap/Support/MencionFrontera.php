<?php

namespace App\Modules\Addons\Roadmap\Support;

/**
 * Item #9990210 — LA DECISIÓN de qué hace una MENCIÓN de una frontera dura.
 *
 * Vive aquí, aislada y sin dependencias, por la misma razón que `CarrilSeguridad`: es una regla de
 * política sobre la frontera dura, y una regla así tiene que poder probarse sin bootear Laravel ni
 * tocar la base (en este proyecto la base de tests es la MISMA de dev — ver el caveat de CLAUDE.md
 * sobre `migrate:fresh --seed`).
 *
 * La regla (decisión de Irving, 2026-09-04): cuando la válvula de contexto sella un item como
 * MENCIÓN —el término sólo se nombra de paso, no se toca—, el item deja de retenerse, SALVO en las
 * categorías que Irving dejó fuera del ablandamiento (`circuito.mencion_retiene_categorias`, por
 * defecto `dinero` y `credenciales`).
 *
 * ⚠️ Esto gobierna SÓLO las menciones. Una ACCIÓN real retiene en las CUATRO categorías, y esa
 * rama ni siquiera llega hasta aquí: `JarvisService::fronteraDuraDeItemDetalle()` sólo consulta
 * este helper dentro del bloque `frontera_valvula === 'mencion'`.
 */
final class MencionFrontera
{
    /** Lista por defecto si la config no se puede leer. Es el lado que RETIENE: falla-segura. */
    public const RETIENEN_POR_DEFECTO = ['dinero', 'credenciales'];

    /** Las cuatro categorías de frontera dura que evalúa la válvula (`config('circuito.jarvis.escalamiento')`). */
    public const CATEGORIAS = ['produccion', 'borrar_datos', 'dinero', 'credenciales'];

    /**
     * ¿Una MENCIÓN de esta categoría sigue reteniendo al item?
     *
     * @param  string|null  $categoria   categoría detectada por la válvula (null = no disparó nada)
     * @param  array|null   $retienen    categorías que retienen aunque sean mención; null = default
     */
    public static function retiene(?string $categoria, ?array $retienen = null): bool
    {
        if ($categoria === null || $categoria === '') {
            return false;   // no disparó ninguna frontera: no hay nada que retener
        }

        // `null` (no se pudo leer la config) y `[]` (Irving la vació a propósito) son cosas
        // DISTINTAS y se tratan distinto:
        //   · null → default, que RETIENE. Un fallo al leer una perilla nunca puede ser la vía por
        //     la que una frontera dura se abra sola.
        //   · []   → decisión explícita de que ninguna categoría retiene una mención. Respetarla es
        //     lo que hace que la perilla signifique lo que su documentación dice.
        $lista = $retienen === null
            ? self::RETIENEN_POR_DEFECTO
            : array_values(array_filter(array_map('strval', $retienen)));

        return in_array($categoria, $lista, true);
    }
}
