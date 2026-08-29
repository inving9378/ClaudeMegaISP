<?php

namespace App\Modules\Addons\Roadmap\Support;

/**
 * GRACIA DE ARRANQUE — la ventana en la que "la base no contesta" todavía no significa
 * "la base desapareció".
 *
 * ── EL INCIDENTE (2026-08-28) ───────────────────────────────────────────────────────────────────
 *
 * El box se reinició a las 20:54:57. La vigilia de Jarvis corre cada minuto desde el boot, así que
 * a las 20:56:03 —66 segundos después— midió `bd_integra` contra un MySQL que aún no aceptaba
 * conexiones. El chequeo del item #228 trata "no pude medir" como el PEOR caso (y hace bien: un
 * chequeo ciego que reporta `ok` es indistinguible de uno que midió y está sano), así que puso el
 * freno automático. MySQL levantó segundos después y la base quedó perfecta —522 tablas—, pero el
 * freno nadie lo suelta solo: sólo lo quita un humano en la Torre. Resultado: las seis terminales
 * detenidas una hora, sin que nada estuviera roto.
 *
 * Esto no era mala suerte: es determinista. Todo reinicio del servidor frena el circuito.
 *
 * ── QUÉ CAMBIA Y QUÉ NO ─────────────────────────────────────────────────────────────────────────
 *
 * NO se ablanda la defensa de #228. Una base que de verdad se vacía sigue frenando igual, porque
 * en ese caso SÍ hay conexión y el conteo de tablas cae: esa rama no se toca.
 *
 * Lo único que cambia es el caso "ni siquiera pude conectarme" DENTRO de los primeros segundos de
 * vida del box, donde la explicación abrumadoramente probable es que la base aún está arrancando.
 * Ahí el chequeo dice `arranque` en vez de `critico`: no frena, pero TAMPOCO reporta `ok` —sigue
 * saliendo como aviso en la Torre—, que es justo la falsa calma que #228 vino a evitar.
 *
 * La exposición máxima es la ventana: si la base muriera de verdad justo al arrancar el box, el
 * freno llega en la corrida siguiente a que se agote la gracia, no antes.
 *
 * ── POR QUÉ ES UNA CLASE APARTE Y SIN FRAMEWORK ─────────────────────────────────────────────────
 *
 * Mismo motivo que el centinela del freno vive en archivo: la decisión de si frenar o no NO puede
 * depender de la base ni de que Laravel bootee, porque el escenario en el que se evalúa es
 * precisamente "nada de eso responde". Es PHP puro y sin imports, así que se puede probar sin
 * `migrate:fresh` y sin tocar la base compartida del entorno.
 */
final class GraciaDeArranque
{
    /**
     * Segundos que lleva encendido el box, leídos de `/proc/uptime` (primer número del archivo).
     *
     * Devuelve `null` si no se puede leer o no es un número. `null` NO es cero: significa "no sé
     * cuánto lleva arriba", y quien decide lo trata como fuera de gracia (ver `enGracia`). Ante la
     * duda, el comportamiento anterior —frenar— es el lado seguro.
     */
    public static function uptimeSegundos(string $ruta = '/proc/uptime'): ?float
    {
        $crudo = @file_get_contents($ruta);
        if ($crudo === false) {
            return null;
        }

        $primero = strtok(trim($crudo), " \t\n");
        if ($primero === false || ! is_numeric($primero)) {
            return null;
        }

        $seg = (float) $primero;

        return $seg >= 0 ? $seg : null;
    }

    /**
     * ¿Estamos dentro de la ventana de gracia posterior al arranque?
     *
     * FAIL-CLOSED en los dos bordes: uptime desconocido (`null`) o gracia no positiva ⇒ `false`,
     * o sea "no hay gracia, trata el fallo como crítico". Un error leyendo `/proc/uptime` nunca
     * puede convertirse en un permiso para ignorar una base caída.
     */
    public static function enGracia(?float $uptimeSeg, int $graciaSeg): bool
    {
        if ($uptimeSeg === null || $graciaSeg <= 0) {
            return false;
        }

        return $uptimeSeg < $graciaSeg;
    }
}
