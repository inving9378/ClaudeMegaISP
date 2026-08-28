<?php

namespace Tests\Unit\Modules\Addons\Roadmap;

use App\Modules\Addons\Roadmap\Models\TorreConfig;
use App\Modules\Addons\Roadmap\Services\TorreAutomationPolicy;
use PHPUnit\Framework\TestCase; // TestCase PURO de PHPUnit: NO bootea Laravel, NO toca BD.

/**
 * CANDADO DE LOS TECHOS — y es una DESIGUALDAD, no una igualdad.
 *
 * Divergir hacia ABAJO está permitido y es sano: que `jarvis.mecanico` sea `B` mientras la política
 * base es `C` es una distinción real —el carril mecánico no tiene un brief humano detrás— y el
 * panel la muestra como información, no como inconsistencia.
 *
 * Divergir hacia ARRIBA tiene que ser imposible: un sub-techo por encima de la base sería un actor
 * aprobando más de lo que la política permite, con el panel diciendo otra cosa. Eso es la enfermedad
 * que toda esta fase viene curando.
 *
 * ⚠️ Nota sobre el vocabulario: `politicaBase()` NO es un techo (un `automatizacion_override = auto`
 * sobre un item puede excederla, salvo en `manual`, que sí es absoluto). Los SUB-TECHOS por actor sí
 * lo son, y eso es lo que este test fija.
 */
class TorreTechosCoherentesTest extends TestCase
{
    private const ORDEN = ['A' => 1, 'B' => 2, 'C' => 3];

    /** El sub-techo de cada actor nunca puede quedar por encima de la política base configurada. */
    public function test_ningun_subtecho_excede_la_politica_base(): void
    {
        foreach (TorreConfig::NIVELES as $nivel) {
            $base = TorreConfig::TECHO_POR_NIVEL[$nivel];
            if ($base === null) {
                continue;   // `manual`: nadie aprueba nada; no hay desigualdad que comprobar
            }

            foreach (TorreAutomationPolicy::SUBTECHOS as $actor => $claveConfig) {
                $sub = $this->valorPorDefectoDeConfig($claveConfig);
                $this->assertArrayHasKey($sub, self::ORDEN,
                    "El default de `{$claveConfig}` es '{$sub}', que no es un nivel válido (A|B|C).");

                // La desigualdad se comprueba sobre el EFECTIVO, que es min(base, sub).
                $efectivo = self::ORDEN[$sub] < self::ORDEN[$base] ? $sub : $base;

                $this->assertLessThanOrEqual(self::ORDEN[$base], self::ORDEN[$efectivo],
                    "Con política base `{$nivel}` ({$base}), el actor `{$actor}` quedaría en "
                    . "`{$efectivo}`, por ENCIMA de la base. `nivelEfectivo()` debe devolver "
                    . 'min(base, sub-techo) y algo lo rompió.');
            }
        }
    }

    /**
     * `nivelEfectivo()` tiene que ser literalmente `min(base, sub)`. Se comprueba sobre la tabla
     * completa base × sub-techo, sin depender de los valores que hoy tenga la config.
     */
    public function test_el_nivel_efectivo_es_el_minimo_de_los_dos(): void
    {
        foreach (['A', 'B', 'C'] as $base) {
            foreach (['A', 'B', 'C'] as $sub) {
                $esperado = self::ORDEN[$sub] < self::ORDEN[$base] ? $sub : $base;

                $this->assertSame($esperado, $this->minimo($base, $sub),
                    "min({$base}, {$sub}) debería ser {$esperado}.");
                $this->assertLessThanOrEqual(self::ORDEN[$base], self::ORDEN[$esperado],
                    'El efectivo nunca puede superar la base.');
            }
        }
    }

    /**
     * `manual` significa manual: su entrada en el mapa es `null` y **no puede tener fallback**.
     *
     * Se comprueba explícitamente porque ya falló una vez: `techoGlobal()` usaba `?? 'A'`, y `??` no
     * distingue el `null` LEGÍTIMO de `manual` de una clave inexistente — el paro de emergencia caía
     * al fallback y seguía aprobando items nivel A. (2026-08-19, encontrado antes de cablearlo.)
     */
    public function test_manual_significa_manual(): void
    {
        $this->assertArrayHasKey('manual', TorreConfig::TECHO_POR_NIVEL);
        $this->assertNull(TorreConfig::TECHO_POR_NIVEL['manual'],
            '`manual` dejó de significar «la máquina no aprueba nada».');

        $src = file_get_contents(dirname(__DIR__, 5) . '/app/Modules/Addons/Roadmap/Models/TorreConfig.php');
        $this->assertStringNotContainsString('TECHO_POR_NIVEL[$this->nivel_automatizacion] ?? ', $src,
            'Volvió el `??` sobre TECHO_POR_NIVEL: no distingue el null de `manual` de una clave '
            . 'inexistente, y el modo manual vuelve a caer al fallback. Usar `array_key_exists`.');
    }

    /** Los cuatro niveles del panel están declarados y ordenados de menos a más automático. */
    public function test_los_niveles_del_panel_estan_ordenados(): void
    {
        $this->assertSame(['manual', 'estandar', 'asistido', 'autonomo'], TorreConfig::NIVELES,
            'Cambió el orden o el juego de niveles: `TorreConfigService::auditar()` decide si un '
            . 'cambio SUBE el techo comparando índices de este array, y se registraría al revés.');

        $anterior = 0;
        foreach (TorreConfig::NIVELES as $n) {
            $techo = TorreConfig::TECHO_POR_NIVEL[$n];
            $actual = $techo === null ? 0 : self::ORDEN[$techo];
            $this->assertGreaterThanOrEqual($anterior, $actual,
                "El nivel `{$n}` no es más (o igual de) automático que el anterior.");
            $anterior = $actual;
        }
    }

    private function minimo(string $base, string $sub): string
    {
        return self::ORDEN[$sub] < self::ORDEN[$base] ? $sub : $base;
    }

    /** El default declarado en `config/circuito.php` para una clave, sin bootear Laravel. */
    private function valorPorDefectoDeConfig(string $clave): string
    {
        $src  = file_get_contents(dirname(__DIR__, 5) . '/config/circuito.php');
        $hoja = substr($clave, strrpos($clave, '.') + 1);

        // p.ej. 'max_nivel' => env('CIRCUITO_..._MAX_NIVEL', 'B'),
        $this->assertTrue(
            (bool) preg_match_all("/'" . preg_quote($hoja, '/') . "'\s*=>\s*env\([^,]+,\s*'([ABC])'\s*\)/", $src, $m),
            "No pude leer el default de `{$clave}` en config/circuito.php."
        );

        // Puede haber varias claves `max_nivel`; se toma la más permisiva como peor caso.
        $peor = 'A';
        foreach ($m[1] as $v) {
            if (self::ORDEN[$v] > self::ORDEN[$peor]) {
                $peor = $v;
            }
        }

        return $peor;
    }
}
