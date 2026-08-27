<?php

namespace Tests\Unit\Modules\Addons\Roadmap;

use PHPUnit\Framework\TestCase; // TestCase PURO de PHPUnit: NO bootea Laravel, NO toca BD.

/**
 * CANDADO 2026-08-27 — la APROBACIÓN MUDA del autopilot no puede volver.
 *
 * Qué pasó: `circuito:autopilot` reportó «2 de 109 tomados al autopilot» e imprimió
 * `#225 [B] → auto-ejecutado ()` —con el estado VACÍO— sin haber movido un solo item. Los dos
 * seguían en `requiere_irving`, y el mismo output los contaba TAMBIÉN bajo «quedan para Irving».
 * Es la familia de las aprobaciones mudas (#32: 8 mudas; #186: 32): el circuito no se cae, se
 * degrada a algo que parece una decisión legítima.
 *
 * Tres defectos, tres candados aquí:
 *
 *  1. `AutopilotService::aplicar()` contestaba las preguntas ANTES de consultar la política. Si la
 *     política rechazaba, el item quedaba con su brief «100 % contestado» por el autopilot y sin
 *     aprobar: parecía decidido y no despachaba nunca.
 *  2. `AutopilotCommand` ramificaba sobre `$r['auto']` (lo que el autopilot OPINÓ) en vez de
 *     `$r['aplicado']` (si de verdad escribió el estado).
 *  3. El `--dry` llamaba a `evaluar()` a secas, que NO consulta la política → prometía items que la
 *     aplicación real rechazaba. El dry y el real tienen que responder la MISMA pregunta.
 *
 * Inspección de fuente — mismo patrón que `RevisorAlcanceNoSeLlamaFronteraDuraTest`.
 */
class AutopilotNoAprobaMudoTest extends TestCase
{
    private function raiz(): string
    {
        return dirname(__DIR__, 5);
    }

    private function fuente(string $rel): string
    {
        $f = $this->raiz() . '/' . $rel;
        $this->assertFileExists($f, "Se movió/renombró {$rel}: actualiza este candado en el mismo commit.");

        return file_get_contents($f);
    }

    private function metodo(string $src, string $nombre): string
    {
        $ini = strpos($src, "function {$nombre}(");
        $this->assertNotFalse($ini, "No se encontró el método {$nombre}(): actualiza este candado.");

        // Desde la firma hasta la llave de cierre al mismo nivel de indentación (4 espacios).
        $resto = substr($src, $ini);
        $fin   = strpos($resto, "\n    }");

        return $fin === false ? $resto : substr($resto, 0, $fin);
    }

    /** DEFECTO 1 — el gate de política va ANTES de escribir las respuestas. */
    public function test_aplicar_consulta_la_politica_antes_de_responder_preguntas(): void
    {
        $cuerpo = $this->metodo(
            $this->fuente('app/Modules/Addons/Roadmap/Services/AutopilotService.php'),
            'aplicar'
        );

        $gate      = strpos($cuerpo, 'evaluarConPolitica');
        $escritura = strpos($cuerpo, 'responderPregunta');

        $this->assertNotFalse($gate, 'aplicar() ya no consulta la política: volvería la aprobación muda.');
        $this->assertNotFalse($escritura, 'aplicar() ya no responde preguntas: revisa este candado.');
        $this->assertLessThan(
            $escritura,
            $gate,
            'REGRESIÓN: aplicar() vuelve a escribir las respuestas ANTES del gate de política. '
            . 'Un item rechazado quedaría con el brief contestado por el autopilot y sin aprobar — '
            . 'decidido a la vista, incapaz de despachar.'
        );
    }

    /** DEFECTO 2 — el comando cuenta lo que se APLICÓ, no lo que se opinó. */
    public function test_el_comando_no_cuenta_por_auto_sino_por_aplicado(): void
    {
        $cuerpo = $this->metodo(
            $this->fuente('app/Modules/Addons/Roadmap/Console/AutopilotCommand.php'),
            'handle'
        );

        $this->assertStringContainsString(
            "\$r['aplicado']",
            $cuerpo,
            'REGRESIÓN: el comando dejó de mirar `aplicado`. Volvería a reportar como '
            . '«auto-ejecutado ()» items que la política rechazó.'
        );

        // El `if` que decide qué se imprime/cuenta no puede volver a ser `if ($r['auto'])`.
        $this->assertDoesNotMatchRegularExpression(
            "/if\s*\(\s*\\\$r\['auto'\]\s*\)/",
            $cuerpo,
            'REGRESIÓN: el comando ramifica otra vez sobre `auto` (lo que el autopilot opinó) '
            . 'en vez de `aplicado` (si de verdad escribió el estado).'
        );
    }

    /** DEFECTO 3 — dry-run y aplicación real responden la MISMA pregunta. */
    public function test_el_dry_run_consulta_la_misma_condicion_que_el_real(): void
    {
        foreach ([
            'app/Modules/Addons/Roadmap/Console/AutopilotCommand.php',
            'app/Modules/Addons/Roadmap/Console/RebriefBandejaCommand.php',
        ] as $rel) {
            $src = $this->fuente($rel);

            $this->assertStringContainsString(
                'evaluarConPolitica(',
                $src,
                "REGRESIÓN en {$rel}: volvió a `evaluar()` a secas, que no mira la política de la "
                . 'Torre. El reporte prometería items que la aplicación real rechaza.'
            );

            $this->assertDoesNotMatchRegularExpression(
                '/->evaluar\(/',
                $src,
                "REGRESIÓN en {$rel}: quedó una llamada a `evaluar()` sin política. "
                . 'Usa `evaluarConPolitica()` — es el punto único del veredicto.'
            );
        }
    }
}
