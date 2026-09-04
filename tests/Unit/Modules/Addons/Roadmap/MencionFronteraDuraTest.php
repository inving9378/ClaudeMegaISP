<?php

namespace Tests\Unit\Modules\Addons\Roadmap;

use App\Modules\Addons\Roadmap\Support\MencionFrontera;
use PHPUnit\Framework\TestCase; // TestCase PURO de PHPUnit: NO bootea Laravel, NO toca BD.

/**
 * CANDADO DE REGRESIÓN — ablandamiento de MENCIONES por categoría (#9990210).
 *
 * Decisión de Irving (2026-09-04): una MENCIÓN de una frontera dura deja de retener, SALVO en
 * `dinero` y `credenciales`. Este test existe para que ese contrato no se afloje en silencio:
 *
 *   · si alguien saca `dinero` o `credenciales` de `config/circuito.php`, truena el primer test —
 *     y lo hace leyendo la config REAL, no una copia, igual que `CarrilSeguridadAutoRegresionTest`;
 *   · si alguien hace que la lista vacía o ilegible deje de retener por accidente, truenan los
 *     falla-segura.
 *
 * Lo que este test NO puede vigilar: que `JarvisService` siga consultando este helper sólo dentro
 * del bloque `frontera_valvula === 'mencion'`. Esa parte se verificó a mano contra la clase real y
 * está anotada en el reporte del item; el día que alguien mueva la llamada fuera de ese bloque, el
 * ablandamiento se filtraría a las ACCIONES y ningún test lo atraparía.
 */
class MencionFronteraDuraTest extends TestCase
{
    /**
     * La config REAL del proyecto, no una copia: si la lista cambia allí, este test lo ve.
     *
     * NO se puede `require` el archivo entero como hace `CarrilSeguridadAutoRegresionTest` con
     * `circuito_hardening.php`: `config/circuito.php:128` llama a `base_path()`, que necesita una
     * Application de Laravel booteada — justo lo que este test evita para no arrastrar el
     * `migrate:fresh --seed` de `Tests\TestCase` contra la base de DEV (caveat de CLAUDE.md).
     * Se lee el DEFAULT publicado en la llamada a `env()`, que es lo que corre cuando nadie define
     * la variable, es decir el comportamiento por omisión del sistema.
     */
    private function categoriasQueRetienenSegunConfig(): array
    {
        $fuente = file_get_contents(dirname(__DIR__, 5) . '/config/circuito.php');
        $this->assertIsString($fuente);
        $this->assertStringContainsString(
            'mencion_retiene_categorias',
            $fuente,
            'La clave `mencion_retiene_categorias` desapareció de config/circuito.php (#9990210).'
        );

        $ok = preg_match("/CIRCUITO_MENCION_RETIENE'\\s*,\\s*'([^']*)'/", $fuente, $m);
        $this->assertSame(1, $ok, 'No se encontró el default de CIRCUITO_MENCION_RETIENE en config/circuito.php.');

        return array_values(array_filter(array_map('trim', explode(',', $m[1]))));
    }

    public function test_dinero_y_credenciales_siguen_reteniendo_una_mencion(): void
    {
        $config = $this->categoriasQueRetienenSegunConfig();

        foreach (['dinero', 'credenciales'] as $cat) {
            $this->assertContains(
                $cat,
                $config,
                "«{$cat}» debe seguir en `mencion_retiene_categorias` de config/circuito.php: es una de "
                . 'las dos categorías que Irving dejó FUERA del ablandamiento (#9990210).'
            );
            $this->assertTrue(
                MencionFrontera::retiene($cat, $config),
                "Una MENCIÓN de «{$cat}» debe seguir reteniendo el item."
            );
        }
    }

    public function test_produccion_y_borrar_datos_dejan_de_retener_una_mencion(): void
    {
        $config = $this->categoriasQueRetienenSegunConfig();

        foreach (['produccion', 'borrar_datos'] as $cat) {
            $this->assertFalse(
                MencionFrontera::retiene($cat, $config),
                "Una MENCIÓN de «{$cat}» NO debe retener: el circuito estaría pagando IA por un "
                . 'veredicto que no cambia ninguna decisión (#9990210).'
            );
        }
    }

    public function test_sin_categoria_no_retiene(): void
    {
        $this->assertFalse(MencionFrontera::retiene(null));
        $this->assertFalse(MencionFrontera::retiene(''));
    }

    /** FALLA-SEGURA: config ilegible (null) → se retiene. Nunca se abre una frontera por un fallo. */
    public function test_config_ilegible_cae_del_lado_que_retiene(): void
    {
        $this->assertTrue(MencionFrontera::retiene('dinero', null));
        $this->assertTrue(MencionFrontera::retiene('credenciales', null));
    }

    /** Vaciarla A PROPÓSITO sí significa «ninguna categoría retiene»: la perilla hace lo que dice. */
    public function test_lista_vacia_a_proposito_deja_pasar_todo(): void
    {
        $this->assertFalse(MencionFrontera::retiene('dinero', []));
        $this->assertFalse(MencionFrontera::retiene('credenciales', []));
    }
}
