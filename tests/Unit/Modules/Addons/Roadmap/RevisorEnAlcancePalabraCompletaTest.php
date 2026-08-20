<?php

namespace Tests\Unit\Modules\Addons\Roadmap;

use PHPUnit\Framework\TestCase; // TestCase PURO de PHPUnit: NO bootea Laravel, NO toca BD.

/**
 * CANDADO #865 — `RevisorService::enAlcance()` no puede volver a `Str::contains`/`str_contains`
 * crudo sobre el denylist de alcance. Es exactamente el defecto que #865 reporta: la lección de
 * #338 («palabra completa, no substring») estaba documentada en CONTEXTO-MEGAISP.md §8.4-quinquies
 * y aun así `enAlcance()` seguía con substring mucho después de que `ThomasService` ya lo aplicaba.
 *
 * Inspección de fuente (no ejecuta el método: `config()`/Eloquent exigirían bootear Laravel, y los
 * tests de este módulo evitan eso a propósito — ver hermanos en este mismo directorio). El
 * candado de comportamiento real vive en `DetectorTerminosPalabraCompletaTest`.
 */
class RevisorEnAlcancePalabraCompletaTest extends TestCase
{
    private function raiz(): string
    {
        return dirname(__DIR__, 5);
    }

    private function fuente(): string
    {
        $f = $this->raiz() . '/app/Modules/Addons/Roadmap/Services/RevisorService.php';
        $this->assertFileExists($f, 'Se movió/renombró RevisorService: actualiza este candado en el mismo commit.');

        return file_get_contents($f);
    }

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

    public function test_en_alcance_no_usa_str_contains_crudo(): void
    {
        $cuerpo = $this->metodo($this->fuente(), 'enAlcance');

        foreach (['Str::contains(', 'str_contains('] as $substring) {
            $this->assertStringNotContainsString($substring, $cuerpo,
                "`enAlcance()` volvió a usar `{$substring}` sobre el denylist — exactamente el bug de "
                . '#865 (substring crudo pese a la lección de #338 ya documentada).');
        }
    }

    public function test_en_alcance_delega_en_la_definicion_unica_de_termino(): void
    {
        $cuerpo = $this->metodo($this->fuente(), 'enAlcance');

        $this->assertStringContainsString('DetectorTerminos::dispara(', $cuerpo,
            "`enAlcance()` dejó de delegar en `DetectorTerminos::dispara()`: el pre-filtro de alcance "
            . 'del revisor y la frontera dura de Thomas volverían a tener dos semánticas distintas.');
    }
}
