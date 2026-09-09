<?php

namespace Tests\Unit\Support;

use App\Support\RolInstancia;
use Tests\TestCase;

/**
 * El default restrictivo es la propiedad que sostiene todo el blindaje de los
 * módulos de operador. Si un día alguien "arregla" el default a 'operador'
 * porque en dev es más cómodo, estas pruebas fallan — que es justo el punto.
 */
class RolInstanciaTest extends TestCase
{
    private function conRol($valor): void
    {
        config(['instancia.rol' => $valor]);
    }

    /** @test */
    public function sin_valor_definido_la_instalacion_es_cliente(): void
    {
        $this->conRol(null);

        $this->assertSame(RolInstancia::CLIENTE, RolInstancia::actual());
        $this->assertFalse(RolInstancia::esOperador());
    }

    /** @test */
    public function un_valor_no_reconocido_cae_a_cliente(): void
    {
        foreach (['OPERADORR', 'admin', 'true', '1', 'meganet', ''] as $basura) {
            $this->conRol($basura);
            $this->assertSame(
                RolInstancia::CLIENTE,
                RolInstancia::actual(),
                "El valor '{$basura}' debía caer a cliente, no abrir la instalación."
            );
        }
    }

    /** @test */
    public function reconoce_operador_sin_importar_mayusculas_ni_espacios(): void
    {
        foreach (['operador', 'OPERADOR', ' Operador '] as $v) {
            $this->conRol($v);
            $this->assertTrue(RolInstancia::esOperador(), "No reconoció '{$v}' como operador.");
        }
    }

    /** @test */
    public function un_modulo_que_no_exige_rol_corre_en_cualquier_instalacion(): void
    {
        // Los 48 módulos ya registrados están en este caso: no declaran
        // instance_role, así que el mecanismo nuevo no cambia su comportamiento.
        $this->conRol(RolInstancia::CLIENTE);

        $this->assertTrue(RolInstancia::satisface(null));
        $this->assertTrue(RolInstancia::satisface(''));
    }

    /** @test */
    public function una_instalacion_de_cliente_no_satisface_un_modulo_de_operador(): void
    {
        $this->conRol(RolInstancia::CLIENTE);
        $this->assertFalse(RolInstancia::satisface('operador'));

        $this->conRol(RolInstancia::OPERADOR);
        $this->assertTrue(RolInstancia::satisface('operador'));
    }

    /** @test */
    public function el_mensaje_de_rechazo_dice_que_hacer(): void
    {
        $this->conRol(RolInstancia::CLIENTE);

        $msg = RolInstancia::mensajeRechazo('addon-voz-mayorista', 'operador');

        $this->assertStringContainsString('addon-voz-mayorista', $msg);
        $this->assertStringContainsString('INSTANCE_ROLE=operador', $msg);
    }
}
