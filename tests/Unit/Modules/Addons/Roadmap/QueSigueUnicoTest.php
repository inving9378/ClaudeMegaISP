<?php

namespace Tests\Unit\Modules\Addons\Roadmap;

use PHPUnit\Framework\TestCase; // TestCase PURO de PHPUnit: NO bootea Laravel, NO toca BD.

/**
 * #1030 paso B — candado del cálculo ÚNICO de «qué sigue».
 *
 * Panorama («Cola ejecutable») y Terminales («Listos para terminal») contaban con reglas propias
 * (`RoadmapItem::autoEjecutable()`, sin dependencias, secciones de fase ni el interruptor del
 * revisor) y llegaron a decir 45 y 11 mientras el despachador no podía sacar ninguno. Ahora las dos
 * leen `RoadmapCircuitoService::queSigue()`, armado con las mismas funciones que usa el despachador.
 * Este candado falla si alguna de las dos vuelve a contar por su cuenta.
 */
class QueSigueUnicoTest extends TestCase
{
    public function test_que_sigue_se_arma_con_las_funciones_del_despachador(): void
    {
        $cuerpo = $this->cuerpoDeMetodo('app/Modules/Addons/Roadmap/Services/RoadmapCircuitoService.php', 'queSigue');

        foreach (['modulosEnVuelo', 'ejecutablesParalelo', 'diagnosticoCeroDespacho'] as $fn) {
            $this->assertStringContainsString($fn . '(', $cuerpo,
                "queSigue() dejó de usar {$fn}(): ya no sería el mismo cálculo que el despachador.");
        }
    }

    public function test_terminales_lee_el_calculo_unico(): void
    {
        $archivo = 'app/Modules/Addons/Roadmap/Services/SupervisorService.php';
        foreach (['listosParaTerminal', 'listosParaTerminalTotal', 'elegiblesReales'] as $metodo) {
            $cuerpo = $this->cuerpoDeMetodo($archivo, $metodo);
            $this->assertMatchesRegularExpression('/queSigue(Items|Total)?\(/', $cuerpo,
                "{$metodo}() dejó de leer queSigue(): Terminales volvería a contar distinto que el despachador.");
            $this->assertStringNotContainsString('autoEjecutable(', $cuerpo,
                "{$metodo}() volvió a usar autoEjecutable(), que no mira dependencias ni el revisor.");
        }
    }

    public function test_panorama_lee_el_calculo_unico(): void
    {
        $cuerpo = $this->cuerpoDeMetodo('app/Modules/Addons/Roadmap/Controllers/RoadmapController.php', 'torre');

        $this->assertStringContainsString('queSigueItems(', $cuerpo,
            'La Cola ejecutable de Panorama dejó de salir de queSigueItems().');
        $this->assertStringContainsString('queSigueTotal(', $cuerpo,
            'El conteo de la cola de Panorama dejó de salir de queSigueTotal().');
        $this->assertStringNotContainsString('autoEjecutable(', $cuerpo,
            'torre() volvió a contar la cola con autoEjecutable(), que no es el cálculo del despachador.');
    }

    // ── helpers ──────────────────────────────────────────────────────────────────────────────

    /** Cuerpo fuente de un método sin comentarios, balanceando llaves desde su `{` de apertura. */
    private function cuerpoDeMetodo(string $relativo, string $metodo): string
    {
        $file = dirname(__DIR__, 5) . '/' . $relativo;
        $src  = file_get_contents($file);
        $this->assertIsString($src, "No pude leer {$relativo}");

        $pos = strpos($src, "function {$metodo}(");
        $this->assertNotFalse($pos, "No encontré `{$metodo}()` en {$relativo} — si se renombró, actualiza este candado.");

        $inicio = strpos($src, '{', $pos);
        $depth  = 0;
        for ($i = $inicio, $len = strlen($src); $i < $len; $i++) {
            if ($src[$i] === '{') {
                $depth++;
            } elseif ($src[$i] === '}' && --$depth === 0) {
                $cuerpo = substr($src, $inicio, $i - $inicio + 1);

                return preg_replace(['#/\*.*?\*/#s', '#//[^\n]*#'], '', $cuerpo);
            }
        }

        $this->fail("No pude delimitar el cuerpo de `{$metodo}()` en {$relativo}.");
    }
}
