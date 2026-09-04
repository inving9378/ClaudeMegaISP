<?php

namespace Tests\Unit\Modules\Addons\Roadmap;

use PHPUnit\Framework\TestCase; // TestCase PURO de PHPUnit: NO bootea Laravel, NO toca BD.

/**
 * Candado de forma para `config/circuito_hardening.php` (#918, sub-item de #880 Pieza 4).
 *
 * Este archivo es SOLO DECLARATIVO — nada lo lee en runtime todavía (eso es la fase de wiring,
 * el sub-item siguiente de #880). Este test es la única verificación que existe hasta entonces:
 * garantiza que el shape se mantiene estable para cuando esa fase llegue.
 */
class CircuitoHardeningCriterioTest extends TestCase
{
    private function config(): array
    {
        $config = require dirname(__DIR__, 5) . '/config/circuito_hardening.php';
        $this->assertIsArray($config);

        return $config;
    }

    public function test_declara_las_cuatro_llaves_esperadas(): void
    {
        $config = $this->config();

        $this->assertSame(
            ['criterio', 'auto_terminos', 'bandeja_excepciones', 'bandeja_terminos_existentes'],
            array_keys($config),
            'Cambió el shape de config/circuito_hardening.php — la fase de wiring de #880 '
                . 'espera exactamente estas 4 llaves.'
        );
    }

    public function test_criterio_declara_auto_if_bandeja_if_y_default_restrictivo(): void
    {
        $criterio = $this->config()['criterio'];

        $this->assertIsArray($criterio);
        $this->assertSame(['auto_if', 'bandeja_if', 'default'], array_keys($criterio));

        foreach (['auto_if', 'bandeja_if'] as $llave) {
            $this->assertIsString($criterio[$llave]);
            $this->assertNotSame('', trim($criterio[$llave]), "`{$llave}` no puede quedar vacío.");
        }

        // #918 punto 3: default restrictivo — cualquier término no listado cae en BANDEJA,
        // nunca al revés.
        $this->assertSame('bandeja', $criterio['default']);
    }

    public function test_auto_terminos_es_la_lista_corta_del_brief_de_opus(): void
    {
        $terminos = $this->config()['auto_terminos'];

        $this->assertIsArray($terminos);
        $this->assertSame(array_values($terminos), $terminos, 'Debe ser un array de lista (no asociativo).');
        $this->assertNotEmpty($terminos);

        foreach ($terminos as $t) {
            $this->assertIsString($t);
            $this->assertNotSame('', trim($t));
        }

        // El secreto hardcodeado NO debe colarse en AUTO — arranca en BANDEJA a propósito.
        $this->assertNotContains('mover_secreto_hardcodeado_a_env', $terminos);
    }

    public function test_bandeja_excepciones_contiene_el_secreto_hardcodeado(): void
    {
        $excepciones = $this->config()['bandeja_excepciones'];

        $this->assertIsArray($excepciones);
        $this->assertContains('mover_secreto_hardcodeado_a_env', $excepciones,
            'El brief de Opus pide ver este término en bandeja unas semanas antes de aflojarlo.');
    }

    public function test_bandeja_terminos_existentes_no_esta_vacia_ni_se_reduce(): void
    {
        $terminos = $this->config()['bandeja_terminos_existentes'];

        $this->assertIsArray($terminos);
        $this->assertNotEmpty($terminos);

        // Referencia informativa de la infra real (#673) que este archivo declara que NO toca.
        foreach (['permiso', 'dinero', 'password', '.env', 'idor'] as $esperado) {
            $this->assertContains($esperado, $terminos);
        }
    }

    public function test_ningun_termino_auto_se_repite_en_bandeja(): void
    {
        $config = $this->config();

        $bandeja = array_merge($config['bandeja_excepciones'], $config['bandeja_terminos_existentes']);
        $interseccion = array_intersect($config['auto_terminos'], $bandeja);

        $this->assertSame([], array_values($interseccion),
            'Un término no puede estar en AUTO y en BANDEJA a la vez — la carga de prueba nunca se invierte.');
    }
}
