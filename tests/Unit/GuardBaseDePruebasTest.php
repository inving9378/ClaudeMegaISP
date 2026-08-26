<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SimpleXMLElement;
use Tests\GuardBaseDePruebas;

/**
 * EL CANDADO DEL CANDADO (incidente del 2026-08-25).
 *
 * No extiende `Tests\TestCase` ni usa `CreatesApplication` a propósito: no arranca la app y no
 * toca la base. Tiene que poder correr aunque MySQL esté caído, que es justo el escenario en el
 * que se descubrió el hueco.
 *
 * Comprueba las tres cosas que, si se caen, devuelven el repo al 18:14: el predicado, que el
 * punto de aplicación siga invocándolo, y que `phpunit.xml` declare una base que lo pase.
 */
class GuardBaseDePruebasTest extends TestCase
{
    public function test_la_base_de_la_app_es_rechazada(): void
    {
        $this->assertFalse(GuardBaseDePruebas::esBaseDePruebas('megaisp'));
        $this->assertFalse(GuardBaseDePruebas::esBaseDePruebas('megaisp_dryrun'));
        $this->assertFalse(GuardBaseDePruebas::esBaseDePruebas('meganet_prod_claude'));
    }

    public function test_solo_pasa_lo_que_se_llama_base_de_pruebas(): void
    {
        $this->assertTrue(GuardBaseDePruebas::esBaseDePruebas('megaisp_test'));
        $this->assertTrue(GuardBaseDePruebas::esBaseDePruebas(':memory:'));

        // Parecido no basta: el sufijo es exacto.
        $this->assertFalse(GuardBaseDePruebas::esBaseDePruebas('megaisp_testing'));
        $this->assertFalse(GuardBaseDePruebas::esBaseDePruebas('test_megaisp'));
    }

    public function test_sin_dato_bloquea_no_deja_pasar(): void
    {
        // Fail-closed: no saber contra qué base se corre `migrate:fresh` es el caso peligroso.
        $this->assertFalse(GuardBaseDePruebas::esBaseDePruebas(null));
        $this->assertFalse(GuardBaseDePruebas::esBaseDePruebas(''));
        $this->assertFalse(GuardBaseDePruebas::esBaseDePruebas('   '));
    }

    public function test_verificar_lanza_y_dice_cual_era_la_base(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/megaisp/');

        GuardBaseDePruebas::verificar('megaisp');
    }

    public function test_el_punto_de_aplicacion_sigue_invocando_al_guard(): void
    {
        // Si alguien quita esta línea de CreatesApplication, el candado deja de existir sin que
        // ninguna prueba se ponga roja. Ésta es esa prueba.
        $fuente = file_get_contents(__DIR__.'/../CreatesApplication.php');

        $this->assertStringContainsString(
            'GuardBaseDePruebas::verificarApp($app)',
            $fuente,
            'CreatesApplication::createApplication() ya no llama al candado de la base de pruebas'
        );
    }

    public function test_phpunit_xml_declara_una_base_que_pasa_el_candado(): void
    {
        $xml = new SimpleXMLElement(file_get_contents(__DIR__.'/../../phpunit.xml'));

        $declarada = null;
        foreach ($xml->php->env as $env) {
            if ((string) $env['name'] === 'DB_DATABASE') {
                $declarada = (string) $env['value'];
            }
        }

        $this->assertNotNull($declarada, 'phpunit.xml no declara DB_DATABASE: la suite caería al .env de la app');
        $this->assertTrue(
            GuardBaseDePruebas::esBaseDePruebas($declarada),
            "phpunit.xml declara DB_DATABASE={$declarada}, que NO es una base de pruebas"
        );
    }
}
