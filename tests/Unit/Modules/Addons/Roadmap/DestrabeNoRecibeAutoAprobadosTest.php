<?php

namespace Tests\Unit\Modules\Addons\Roadmap;

use PHPUnit\Framework\TestCase; // TestCase PURO de PHPUnit: NO bootea Laravel, NO toca BD.

/**
 * CANDADO DEL INVARIANTE DEL CARRIL «YA DECIDIDO».
 *
 * `JarvisService::evaluarYaDecidido()` aprueba un item cuyo brief está 100 % contestado. Pero
 * «contestado» NO implica «contestado por un humano»: `AutopilotService::aplicar()` escribe
 * `opcion_elegida` con sus propias respuestas.
 *
 * Lo único que impide que un item auto-contestado reentre por ese carril y se re-apruebe es el
 * FILTRO de su único llamador, `DestrabarCommand`: sólo alimenta items en
 * `requiere_irving | pendiente_revision | aprobado_irving` (+ parqueados y anti-bucle), y el
 * autopilot los deja en `aprobado_claude` / `aprobado_revisor`, que están FUERA de ese conjunto.
 *
 * **La seguridad de un método vive en otro archivo.** Quien lea `evaluarYaDecidido` no se entera, y
 * el día que alguien amplíe ese filtro por una razón perfectamente razonable el agujero se abre sin
 * que nada avise. Este test es lo que avisa.
 *
 * Si el filtro tiene que crecer, que crezca — pero con este test actualizado a propósito y en el
 * mismo commit, no por accidente.
 */
class DestrabeNoRecibeAutoAprobadosTest extends TestCase
{
    /** Estados en los que el AUTOPILOT (y el carril mecánico) dejan lo que aprueban. */
    private const ESTADOS_APROBADOS_POR_MAQUINA = ['aprobado_claude', 'aprobado_revisor'];

    private function fuente(string $rel): string
    {
        $f = dirname(__DIR__, 5) . '/' . $rel;
        $this->assertFileExists($f, "No encontré {$rel}: ¿se movió? Actualiza este candado en el mismo commit.");

        return file_get_contents($f);
    }

    /**
     * El filtro del des-trabador NO puede incluir los estados en los que la máquina deja lo que
     * aprueba. Si los incluyera, un item que el autopilot ya decidió volvería a pasar por
     * `evaluarYaDecidido`, que lo vería «100 % contestado» —porque el propio autopilot lo
     * contestó— y lo re-aprobaría sin intervención humana en ningún punto del ciclo.
     */
    public function test_el_filtro_del_destrabador_excluye_lo_ya_aprobado_por_la_maquina(): void
    {
        $src = $this->fuente('app/Modules/Addons/Roadmap/Console/DestrabarCommand.php');

        // El `whereIn` de estados elegibles del comando.
        $this->assertTrue(
            (bool) preg_match('/whereIn\(\s*[\'"]estado_aprobacion[\'"]\s*,\s*\[(.*?)\]/s', $src, $m),
            'No encontré el `whereIn(estado_aprobacion, [...])` de DestrabarCommand: se reescribió el '
            . 'filtro del que depende la seguridad de `evaluarYaDecidido`. Revísalo a mano.'
        );

        $lista = $m[1];
        foreach (self::ESTADOS_APROBADOS_POR_MAQUINA as $estado) {
            $this->assertStringNotContainsString($estado, $lista,
                "El filtro de `DestrabarCommand` ahora incluye `{$estado}`, que es donde el AUTOPILOT "
                . "deja lo que aprueba. Con eso, un item auto-contestado reentra al carril «ya decidido», "
                . "que lo ve «100 % contestado» (porque lo contestó el autopilot) y lo re-aprueba: "
                . "ninguna persona habría decidido nada en todo el ciclo.\n"
                . 'Si ampliar el filtro es a propósito, `evaluarYaDecidido` necesita su propio guard '
                . 'de procedencia ANTES de que este test se relaje.');
        }
    }

    /**
     * El carril sigue rechazando el brief vacío. Sin esta guarda, un item nivel C sin una sola
     * pregunta cumpliría «no quedan preguntas pendientes» trivialmente.
     */
    public function test_el_carril_rechaza_el_brief_vacio(): void
    {
        $src = $this->fuente('app/Modules/Addons/Roadmap/Services/JarvisService.php');
        $cuerpo = $this->metodo($src, 'evaluarYaDecidido');

        $this->assertMatchesRegularExpression('/if \(\s*!\s*\$preguntas\s*\)/', $cuerpo,
            'Se quitó la guarda del brief vacío de `evaluarYaDecidido`. Sin ella, un item sin una sola '
            . 'pregunta cumple «no quedan preguntas pendientes» de forma trivial y se aprueba solo.');
    }

    /**
     * El LECTOR es defensivo: una pregunta sin opciones no cuenta como contestada. Hoy el escritor
     * (`parsePreguntas`) ya descarta esas preguntas, pero esa asimetría escritor-defensivo /
     * lector-confiado es justo la que se rompe cuando aparece una vía de escritura nueva.
     */
    public function test_una_pregunta_sin_opciones_no_cuenta_como_contestada(): void
    {
        $cuerpo = $this->metodo(
            $this->fuente('app/Modules/Addons/Roadmap/Services/JarvisService.php'),
            'evaluarYaDecidido'
        );

        $this->assertStringContainsString("empty(\$p['opciones'])", $cuerpo,
            'El lector volvió a confiar: sin el guard de `opciones` vacías, una pregunta sin opciones '
            . 'pasa de largo y el item se aprueba sin que nadie haya decidido nada.');
    }

    /** Cuerpo fuente de un método, balanceando llaves. */
    private function metodo(string $src, string $nombre): string
    {
        $pos = strpos($src, "function {$nombre}(");
        $this->assertNotFalse($pos, "No encontré `{$nombre}()` — ¿se renombró?");

        $inicio = strpos($src, '{', $pos);
        $depth  = 0;
        for ($i = $inicio, $len = strlen($src); $i < $len; $i++) {
            if ($src[$i] === '{') {
                $depth++;
            } elseif ($src[$i] === '}' && --$depth === 0) {
                return substr($src, $inicio, $i - $inicio + 1);
            }
        }

        $this->fail("No pude delimitar `{$nombre}()`.");
    }
}
