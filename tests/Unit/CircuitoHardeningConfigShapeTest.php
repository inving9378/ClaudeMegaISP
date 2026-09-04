<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Candado de regresión para config/circuito_hardening.php (#918/#9990007).
 *
 * Solo valida el SHAPE del array declarativo — nadie lo consume todavía (eso es la fase de
 * wiring, item futuro). No extiende Tests\TestCase ni arranca la app: `require` directo del
 * archivo de config, igual que GuardBaseDePruebasTest evita depender de MySQL.
 */
class CircuitoHardeningConfigShapeTest extends TestCase
{
    private function cargarConfig(): array
    {
        return require __DIR__.'/../../config/circuito_hardening.php';
    }

    public function test_declara_las_claves_de_primer_nivel_esperadas(): void
    {
        $config = $this->cargarConfig();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('criterio', $config);
        $this->assertArrayHasKey('auto_terminos', $config);
        $this->assertArrayHasKey('bandeja_excepciones', $config);
        $this->assertArrayHasKey('bandeja_terminos_existentes', $config);
    }

    public function test_criterio_declara_auto_if_bandeja_if_y_default_bandeja(): void
    {
        $criterio = $this->cargarConfig()['criterio'];

        $this->assertIsArray($criterio);
        $this->assertArrayHasKey('auto_if', $criterio);
        $this->assertArrayHasKey('bandeja_if', $criterio);
        $this->assertArrayHasKey('default', $criterio);
        $this->assertIsString($criterio['auto_if']);
        $this->assertIsString($criterio['bandeja_if']);

        // Default restrictivo a propósito (#918 punto 3): nunca se invierte la carga de prueba.
        $this->assertSame('bandeja', $criterio['default']);
    }

    public function test_auto_terminos_es_lista_de_strings_no_vacia(): void
    {
        $terminos = $this->cargarConfig()['auto_terminos'];

        $this->assertIsArray($terminos);
        $this->assertNotEmpty($terminos);

        foreach ($terminos as $termino) {
            $this->assertIsString($termino);
            $this->assertNotSame('', trim($termino));
        }
    }

    public function test_bandeja_excepciones_y_bandeja_terminos_existentes_son_listas_de_strings(): void
    {
        $config = $this->cargarConfig();

        foreach (['bandeja_excepciones', 'bandeja_terminos_existentes'] as $clave) {
            $this->assertIsArray($config[$clave], "config('circuito_hardening.{$clave}') debe ser un array");

            foreach ($config[$clave] as $termino) {
                $this->assertIsString($termino);
            }
        }
    }

    public function test_auto_terminos_y_bandeja_excepciones_no_se_traslapan(): void
    {
        // Un término en ambas listas sería una contradicción del criterio (¿auto o bandeja?).
        $config = $this->cargarConfig();

        $traslape = array_intersect($config['auto_terminos'], $config['bandeja_excepciones']);

        $this->assertEmpty(
            $traslape,
            'auto_terminos y bandeja_excepciones no deben compartir términos: '.implode(', ', $traslape)
        );
    }
}
