<?php

namespace Tests\Unit\Services\Ipv6;

use App\Services\Ipv6\Ipv6AddressPlanCalculator;
use PHPUnit\Framework\TestCase; // TestCase PURO de PHPUnit: NO toca BD, NO migrate:fresh.

class Ipv6AddressPlanCalculatorTest extends TestCase
{
    public function test_prefijo_48_produce_delegacion_56_y_estructura_esperada(): void
    {
        $plan = Ipv6AddressPlanCalculator::calcular('2001:db8::/48', 10, 1.5, ['distrito-norte', 'distrito-sur']);

        $this->assertSame('2001:db8::/48', $plan['prefijo_entrada']);
        $this->assertSame(15, $plan['clientes_proyectados']);
        $this->assertSame('2001:db8::/56', $plan['segmentos']['infraestructura']['prefijo']);
        $this->assertSame('2001:db8:0:100::/56', $plan['segmentos']['enlaces_p2p_reserva']['prefijo']);
        $this->assertSame('2001:db8:0:1000::/52', $plan['segmentos']['zonas'][0]['prefijo']);
        $this->assertSame(56, $plan['segmentos']['zonas'][0]['delegacion']);
        $this->assertSame(16, $plan['segmentos']['zonas'][0]['capacidad_delegaciones']);
        $this->assertSame('2001:db8:0:2000::/52', $plan['segmentos']['zonas'][1]['prefijo']);
        $this->assertSame('2001:db8:0:3000::/52', $plan['segmentos']['empresarial']['prefijo']);
        $this->assertSame(56, $plan['segmentos']['empresarial']['delegacion']);
        $this->assertCount(12, $plan['segmentos']['reservado']['bloques_libres']);
    }

    public function test_prefijo_44_produce_delegacion_60(): void
    {
        $plan = Ipv6AddressPlanCalculator::calcular('2001:db8::/44', 2000, 1.5, ['norte']);

        $this->assertSame(60, $plan['segmentos']['zonas'][0]['delegacion']);
        $this->assertSame(4096, $plan['segmentos']['zonas'][0]['capacidad_delegaciones']);
    }

    public function test_prefijo_40_produce_delegacion_64(): void
    {
        $plan = Ipv6AddressPlanCalculator::calcular('2001:db8::/40', 100000, 1.5, ['norte']);

        $this->assertSame(64, $plan['segmentos']['zonas'][0]['delegacion']);
    }

    public function test_zonas_no_se_traslapan_entre_si_ni_con_infraestructura(): void
    {
        $plan = Ipv6AddressPlanCalculator::calcular('2001:db8::/48', 5, 1.2, ['a', 'b', 'c']);

        $prefijos = [
            $plan['segmentos']['infraestructura']['prefijo'],
            $plan['segmentos']['enlaces_p2p_reserva']['prefijo'],
            ...array_column($plan['segmentos']['zonas'], 'prefijo'),
            $plan['segmentos']['empresarial']['prefijo'],
            ...$plan['segmentos']['reservado']['bloques_libres'],
        ];

        $this->assertSame(count($prefijos), count(array_unique($prefijos)), 'No debe haber prefijos repetidos (traslape).');
    }

    public function test_prefijo_invalido_lanza_excepcion(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('mal formado');
        Ipv6AddressPlanCalculator::calcular('esto-no-es-ipv6/48', 1, 1.0, ['a']);
    }

    public function test_prefijo_sin_longitud_lanza_excepcion(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Ipv6AddressPlanCalculator::calcular('2001:db8::', 1, 1.0, ['a']);
    }

    public function test_longitud_fuera_de_rango_lanza_excepcion(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('no soportada');
        Ipv6AddressPlanCalculator::calcular('2001:db8::/60', 1, 1.0, ['a']);
    }

    public function test_longitud_no_alineada_a_nibble_lanza_excepcion(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('nibble');
        Ipv6AddressPlanCalculator::calcular('2001:db8::/50', 1, 1.0, ['a']);
    }

    public function test_zonas_que_no_caben_lanzan_excepcion(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('no caben');
        Ipv6AddressPlanCalculator::calcular('2001:db8::/48', 1, 1.0, array_fill(0, 15, 'zona'));
    }

    public function test_sin_zonas_lanza_excepcion(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Ipv6AddressPlanCalculator::calcular('2001:db8::/48', 1, 1.0, []);
    }

    public function test_delegacion_que_excederia_el_bloque_lanza_excepcion(): void
    {
        // Un prefijo /56 deja una zona /60 con capacidad máxima de 16 delegaciones /64;
        // pedir 100 millones de clientes proyectados es imposible de delegar sin que el
        // bloque resultante deje de ser menor (más específico) que /64.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('no sería menor');
        Ipv6AddressPlanCalculator::calcular('2001:db8::/56', 100000000, 2.0, ['a']);
    }

    public function test_clientes_negativos_lanza_excepcion(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Ipv6AddressPlanCalculator::calcular('2001:db8::/48', -1, 1.0, ['a']);
    }

    public function test_margen_no_positivo_lanza_excepcion(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Ipv6AddressPlanCalculator::calcular('2001:db8::/48', 1, 0.0, ['a']);
    }
}
