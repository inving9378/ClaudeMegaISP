<?php

namespace Tests\Unit\Modules\Addons\Roadmap;

use PHPUnit\Framework\TestCase; // TestCase PURO de PHPUnit: NO bootea Laravel, NO toca BD.

/**
 * CANDADO #944 — el motivo que devuelve `RevisorService::enAlcance()` no puede volver a llamarse a
 * sí mismo "frontera dura" a secas.
 *
 * `enAlcance()` es el PRE-FILTRO PROPIO del Revisor (`config('circuito.revisor.alcance.denylist')`),
 * una lista de 40 términos DISTINTA de la frontera dura real del circuito
 * (`thomas.escalamiento` / `ThomasService::categoriaFronteraDura()`). Antes de #944 el motivo que
 * ve Irving en el log del item decía literalmente "(frontera dura dinero/seguridad/prod/negocio)",
 * como si esta lista FUERA la frontera dura — y de hecho causó una escalación falsa del propio
 * item #944 ("menciona 'permiso' (frontera dura...)").
 *
 * Esto es SOLO un rename de texto (item #944, opción aprobada por Irving): la lógica de matching
 * (`DetectorTerminos::dispara`, corregida por #865) y la migración de esta denylist a la frontera
 * de Thomas (fuera de alcance — decisión de diseño aparte, `plan-configuracion-torre.md` fase 6)
 * NO se tocan aquí.
 *
 * Inspección de fuente — mismo patrón que `RevisorEnAlcancePalabraCompletaTest` (no bootea Laravel).
 */
class RevisorAlcanceNoSeLlamaFronteraDuraTest extends TestCase
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

    public function test_motivo_de_en_alcance_no_dice_frontera_dura_a_secas(): void
    {
        $cuerpo = $this->metodo($this->fuente(), 'enAlcance');

        // Prohibido el texto EXACTO que mentía. `str_contains` con la frase completa entre
        // paréntesis: no prohíbe la palabra "frontera dura" en general (el docblock la usa para
        // ACLARAR la distinción), solo la afirmación bare de que esta denylist ES la frontera dura.
        $this->assertStringNotContainsString('(frontera dura dinero/seguridad/prod/negocio)', $cuerpo,
            '`enAlcance()` volvió a devolver el motivo viejo, que se llamaba a sí mismo "frontera '
            . 'dura" — exactamente el control que #944 documentó como "el que miente": esta denylist '
            . 'es un prefiltro PROPIO del Revisor, distinto de `thomas.escalamiento`.');
        $this->assertStringNotContainsString('sin términos de frontera dura', $cuerpo,
            '`enAlcance()` volvió a decir "sin términos de frontera dura" en el motivo de "en alcance" '
            . '— mismo problema en la rama contraria del if.');
    }

    public function test_motivo_de_en_alcance_se_distingue_de_la_frontera_de_thomas(): void
    {
        $cuerpo = $this->metodo($this->fuente(), 'enAlcance');

        $this->assertStringContainsString('denylist propia', $cuerpo,
            '`enAlcance()` dejó de aclarar en el motivo que esta denylist es PROPIA del Revisor, '
            . 'distinta de la frontera dura de Thomas — el punto central del rename de #944.');
    }
}
