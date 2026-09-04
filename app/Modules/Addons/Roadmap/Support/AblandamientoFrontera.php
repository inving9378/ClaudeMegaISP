<?php

namespace App\Modules\Addons\Roadmap\Support;

/**
 * Fase 3 de #9990210 (item #9990246) — LA DECISIÓN pura de «¿este item avanzó porque la válvula lo
 * ablandó, y si es así, qué se registra en `torre_frontera_dura_eventos`?».
 *
 * Vive aislada, sin Laravel ni BD, por la misma razón que `MencionFrontera`/`CarrilSeguridad`: es
 * una regla sobre la frontera dura y tiene que poder probarse sin bootear la app (la base de tests
 * de este worktree resuelve a la de dev — ver `tests/GuardBaseDePruebas.php`).
 *
 * `JarvisService::fronteraDuraDeItemDetalle()` puede devolver `categoria=null` (nada retiene al
 * item) con `categoria_detectada` no-null y `ablandada=true`: la válvula selló el item como MENCIÓN
 * (o, en modo `apagar`, cualquier mención) y esa categoría concreta no está entre las que retienen
 * (`MencionFrontera`) — el item SIGUE. Antes de este item ese camino no dejaba ningún rastro: se
 * veía igual que un item que nunca disparó ninguna frontera.
 *
 * `TorreAutomationPolicy::estadoInicial()` llama a `evento()` con el mismo `$det` que ya calculó
 * (una sola vez, ver su docblock) y, si no es `null`, hace el `INSERT` (la parte con efectos, que
 * sí vive en `TorreAutomationPolicy` porque necesita el modelo Eloquent y la dedup por BD).
 */
final class AblandamientoFrontera
{
    /**
     * Tercer valor de `torre_frontera_dura_eventos.veredicto`, junto a `mencion`/`accion` (los dos
     * que SÍ retienen). 18 caracteres — cabe en el `string(20)` de la migración.
     */
    public const VEREDICTO = 'ablandamiento_paso';

    /**
     * @param  array{categoria:?string, categoria_detectada:?string, termino:?string, ablandada?:bool}  $det  el mismo array que devuelve `JarvisService::fronteraDuraDeItemDetalle()`
     * @return array{categoria:string, termino:string, veredicto:string}|null  null = no corresponde registrar nada
     */
    public static function evento(array $det): ?array
    {
        // Si `categoria` no es null, el item SÍ quedó retenido — ese camino lo cubre
        // `TorreAutomationPolicy::registrarFronteraDuraVivo()`, no éste.
        if (($det['categoria'] ?? null) !== null) {
            return null;
        }

        // Sin `ablandada=true` no fue la válvula la que dejó pasar el item (pudo ser que
        // sencillamente no disparara ninguna frontera, o que la categoría estuviera en modo
        // `avisar` — ninguno de los dos es «ablandamiento»).
        if (($det['ablandada'] ?? false) !== true) {
            return null;
        }

        $categoria = trim((string) ($det['categoria_detectada'] ?? ''));
        $termino   = trim((string) ($det['termino'] ?? ''));
        if ($categoria === '' || $termino === '') {
            return null;
        }

        return [
            'categoria' => $categoria,
            'termino'   => $termino,
            'veredicto' => self::VEREDICTO,
        ];
    }
}
