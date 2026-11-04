<?php

namespace Tests\Unit\Metricas;

use App\Services\Metricas\BloqueIpv4;
use PHPUnit\Framework\TestCase;

/** Item #988: el bloque entregado al dedicado, capturado a mano en CIDR. */
class BloqueIpv4Test extends TestCase
{
    public function test_bloque_de_29(): void
    {
        $b = BloqueIpv4::desde('203.0.113.120/29');

        $this->assertSame('203.0.113.120/29', $b->cidr());
        $this->assertSame(['203.0.113.121', '203.0.113.122', '203.0.113.123', '203.0.113.124', '203.0.113.125', '203.0.113.126'], $b->hosts());
        $this->assertTrue($b->esHost('203.0.113.123'));
        $this->assertFalse($b->esHost('203.0.113.120'), 'la de red no es utilizable');
        $this->assertFalse($b->esHost('203.0.113.127'), 'la de broadcast no es utilizable');
        $this->assertFalse($b->esHost('203.0.113.129'), 'fuera del bloque');
    }

    public function test_con_ip_de_host_se_normaliza_a_la_red(): void
    {
        $this->assertSame('203.0.113.128/29', BloqueIpv4::desde('203.0.113.133 / 29')->cidr());
    }

    public function test_31_y_32_usan_todas_sus_ips(): void
    {
        $this->assertSame(['10.0.0.4', '10.0.0.5'], BloqueIpv4::desde('10.0.0.4/31')->hosts());
        $this->assertSame(['10.0.0.9'], BloqueIpv4::desde('10.0.0.9/32')->hosts());
        $this->assertTrue(BloqueIpv4::desde('10.0.0.9/32')->esHost('10.0.0.9'));
    }

    /** @dataProvider invalidos */
    public function test_cidr_invalido(?string $cidr): void
    {
        $this->assertNull(BloqueIpv4::desde($cidr));
    }

    public static function invalidos(): array
    {
        return [
            'vacío'        => [''],
            'nulo'         => [null],
            'sin prefijo'  => ['203.0.113.120'],
            'octeto malo'  => ['203.0.113.300/29'],
            'prefijo malo' => ['203.0.113.120/33'],
            'texto'        => ['bloque del cliente'],
        ];
    }
}
