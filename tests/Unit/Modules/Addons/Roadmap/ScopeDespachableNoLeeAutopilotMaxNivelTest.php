<?php

namespace Tests\Unit\Modules\Addons\Roadmap;

use PHPUnit\Framework\TestCase; // TestCase PURO de PHPUnit: NO bootea Laravel, NO toca BD.

/**
 * CANDADO #944 — `RoadmapItem::scopeDespachable()` no puede volver a leer
 * `config('circuito.autopilot.max_nivel')` como el tope de despacho de TODOS los actores.
 *
 * Ese era exactamente el control que "mentía" (`docs/circuito/inventario-de-controles.md`
 * §«Los que mienten»): la clave se llamaba «autopilot» pero gobernaba a todo el pool desde aquí.
 * La Entrega 1 (`TorreAutomationPolicy` + tabla `torre_config`) ya lo corrigió: el tope global sale
 * de `TorreAutomationPolicy::politicaBase()`, y `autopilot.max_nivel` volvió a ser literal (solo el
 * sub-techo del autopilot, leído por `AutopilotService`/`AutopilotCommand`, que sí le pertenece).
 *
 * Inspección de fuente (no ejecuta el scope: requiere el contenedor de Laravel — `app(...)` — y
 * consultas a `torre_config`/`settings`, y los tests de este módulo evitan bootear Laravel/BD a
 * propósito, ver hermanos en este mismo directorio).
 */
class ScopeDespachableNoLeeAutopilotMaxNivelTest extends TestCase
{
    private function raiz(): string
    {
        return dirname(__DIR__, 5);
    }

    private function fuente(): string
    {
        $f = $this->raiz() . '/app/Modules/Addons/Roadmap/Models/RoadmapItem.php';
        $this->assertFileExists($f, 'Se movió/renombró RoadmapItem: actualiza este candado en el mismo commit.');

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

    /**
     * El cuerpo del método SÍ menciona `autopilot.max_nivel` en un comentario explicativo (por
     * qué ya no se lee de ahí) — eso es documentación, no una regresión. Lo que este candado
     * prohíbe es la LECTURA real: `config('circuito.autopilot.max_nivel'` (o con comillas dobles).
     */
    public function test_scope_despachable_no_lee_autopilot_max_nivel(): void
    {
        $cuerpo = $this->metodo($this->fuente(), 'scopeDespachable');

        foreach (["config('circuito.autopilot.max_nivel'", 'config("circuito.autopilot.max_nivel"'] as $lectura) {
            $this->assertStringNotContainsString($lectura, $cuerpo,
                '`scopeDespachable()` volvió a leer `circuito.autopilot.max_nivel` como tope de '
                . 'despacho de TODOS los actores — exactamente el control que #944 documentó como '
                . '"el que miente": esa clave dice «autopilot» y no debe gobernar más que al '
                . 'autopilot. El tope global vive en `TorreAutomationPolicy::politicaBase()`.');
        }
    }

    public function test_scope_despachable_delega_el_techo_en_torre_automation_policy(): void
    {
        $cuerpo = $this->metodo($this->fuente(), 'scopeDespachable');

        $this->assertStringContainsString('TorreAutomationPolicy::class)->politicaBase()', $cuerpo,
            '`scopeDespachable()` dejó de leer el techo desde `TorreAutomationPolicy::politicaBase()` '
            . '— si el tope global se mueve a otra fuente, este candado debe actualizarse a propósito '
            . 'en el mismo commit, no quedar huérfano.');
    }

    /**
     * Candado de estructura: el techo sigue traduciéndose a un slice de `['A','B','C']` con
     * `ORDEN_NIVEL`, y `null` (política `manual`) sigue significando "nada automático despacha".
     * Si esto cambia sin querer, el gate de nivel del despacho podría dejar de existir en silencio.
     */
    public function test_scope_despachable_conserva_el_gate_de_nivel_manual_vacio(): void
    {
        $cuerpo = $this->metodo($this->fuente(), 'scopeDespachable');

        $this->assertStringContainsString('$base === null', $cuerpo,
            '`scopeDespachable()` dejó de comprobar la política `manual` (`politicaBase() === null`) '
            . 'antes de calcular los niveles despachables.');
        $this->assertStringContainsString("[]", $cuerpo,
            '`scopeDespachable()` dejó de devolver un conjunto de niveles VACÍO en modo `manual` — '
            . 'sin esto, `manual` dejaría de significar "nada automático despacha".');
    }
}
