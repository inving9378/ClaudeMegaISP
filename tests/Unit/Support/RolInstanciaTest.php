<?php

namespace Tests\Unit\Support;

use App\Support\RolInstancia;
use PHPUnit\Framework\TestCase;

/**
 * TestCase PURO de PHPUnit: NO bootea Laravel y NO toca la base.
 *
 * Se prueban `normalizar()` y `satisfaceCon()`, que son funciones puras. La
 * regla que sostiene todo el blindaje —el default restrictivo— hay que poder
 * verificarla barata y a menudo; con `Tests\TestCase` cada corrida arrastra un
 * `migrate:fresh --seed` de ocho minutos para comparar cadenas.
 *
 * Si alguien cambia el default a 'operador' porque en dev es más cómodo, estas
 * pruebas fallan. Ese es el punto.
 */
class RolInstanciaTest extends TestCase
{
    /** @test */
    public function sin_valor_definido_la_instalacion_es_cliente(): void
    {
        $this->assertSame(RolInstancia::CLIENTE, RolInstancia::normalizar(null));
    }

    /** @test */
    public function un_valor_no_reconocido_cae_a_cliente(): void
    {
        foreach (['OPERADORR', 'admin', 'true', '1', 'meganet', '', '  ', 'operator'] as $basura) {
            $this->assertSame(
                RolInstancia::CLIENTE,
                RolInstancia::normalizar($basura),
                "El valor '{$basura}' debía caer a cliente, no abrir la instalación."
            );
        }
    }

    /** @test */
    public function reconoce_operador_sin_importar_mayusculas_ni_espacios(): void
    {
        foreach (['operador', 'OPERADOR', ' Operador ', "\toperador\n"] as $v) {
            $this->assertSame(
                RolInstancia::OPERADOR,
                RolInstancia::normalizar($v),
                "No reconoció " . var_export($v, true) . " como operador."
            );
        }
    }

    /** @test */
    public function un_modulo_que_no_exige_rol_corre_en_cualquier_instalacion(): void
    {
        // Los 48 módulos ya registrados están en este caso: no declaran
        // instance_role, así que el mecanismo nuevo no cambia su comportamiento.
        foreach ([RolInstancia::CLIENTE, RolInstancia::OPERADOR] as $instalacion) {
            $this->assertTrue(RolInstancia::satisfaceCon($instalacion, null));
            $this->assertTrue(RolInstancia::satisfaceCon($instalacion, ''));
        }
    }

    /** @test */
    public function una_instalacion_de_cliente_no_satisface_un_modulo_de_operador(): void
    {
        $this->assertFalse(RolInstancia::satisfaceCon(RolInstancia::CLIENTE, 'operador'));
        $this->assertTrue(RolInstancia::satisfaceCon(RolInstancia::OPERADOR, 'operador'));
    }

    /** @test */
    public function el_mensaje_de_rechazo_dice_que_hacer(): void
    {
        $msg = RolInstancia::mensajeRechazo('addon-voz-mayorista', 'operador', RolInstancia::CLIENTE);

        $this->assertStringContainsString('addon-voz-mayorista', $msg);
        $this->assertStringContainsString('INSTANCE_ROLE=operador', $msg);
    }
}
