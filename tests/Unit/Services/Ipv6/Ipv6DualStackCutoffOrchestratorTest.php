<?php

namespace Tests\Unit\Services\Ipv6;

use App\Services\Ipv6\Ipv6DualStackCutoffOrchestrator;
use PHPUnit\Framework\TestCase; // TestCase PURO de PHPUnit: NO toca BD, NO migrate:fresh.

class Ipv6DualStackCutoffOrchestratorTest extends TestCase
{
    public function test_genera_el_par_de_comandos_ipv4_e_ipv6_para_version_reconocida(): void
    {
        $orquestador = new Ipv6DualStackCutoffOrchestrator();

        $resultado = $orquestador->generarComandos('10.20.30.40', '2001:db8:aaaa:1::/64', '7.14.2', 'Cliente Prueba-123');

        $this->assertSame(
            '/ip firewall address-list add list=MgNet_Morosos address=10.20.30.40 comment="Cliente Prueba-123"',
            $resultado['comando_ipv4']
        );
        $this->assertSame(
            '/ipv6 firewall address-list add list=MgNet_Morosos_V6 address=2001:db8:aaaa:1::/64 comment="Cliente Prueba-123"',
            $resultado['comando_ipv6']
        );
        $this->assertSame([$resultado['comando_ipv4'], $resultado['comando_ipv6']], $resultado['comandos']);
        $this->assertSame([], $resultado['advertencias']);
    }

    public function test_mismo_par_de_comandos_en_las_tres_familias_reales(): void
    {
        $orquestador = new Ipv6DualStackCutoffOrchestrator();

        foreach (['6.49.6', '7.6', '7.14.2'] as $version) {
            $resultado = $orquestador->generarComandos('10.0.0.5', '2001:db8::1/48', $version, 'x');
            $this->assertStringContainsString('list=MgNet_Morosos address=10.0.0.5', $resultado['comando_ipv4']);
            $this->assertStringContainsString('list=MgNet_Morosos_V6 address=2001:db8::1/48', $resultado['comando_ipv6']);
            $this->assertSame([], $resultado['advertencias'], "Versión {$version} no debe traer advertencias en el flujo normal.");
        }
    }

    public function test_version_no_reconocida_no_genera_comando_ipv6_pero_si_ipv4(): void
    {
        $orquestador = new Ipv6DualStackCutoffOrchestrator();

        $resultado = $orquestador->generarComandos('10.0.0.5', '2001:db8::1/48', 'version-invalida', 'x');

        $this->assertNotNull($resultado['comando_ipv4']);
        $this->assertNull($resultado['comando_ipv6']);
        $this->assertSame([$resultado['comando_ipv4']], $resultado['comandos']);
        $this->assertNotEmpty($resultado['advertencias']);
    }

    public function test_ipv4_invalida_lanza_excepcion(): void
    {
        $orquestador = new Ipv6DualStackCutoffOrchestrator();

        $this->expectException(\InvalidArgumentException::class);
        $orquestador->generarComandos('no-es-una-ip', '2001:db8::1/48', '7.14.2', 'x');
    }

    public function test_prefijo_ipv6_sin_longitud_lanza_excepcion(): void
    {
        $orquestador = new Ipv6DualStackCutoffOrchestrator();

        $this->expectException(\InvalidArgumentException::class);
        $orquestador->generarComandos('10.0.0.5', '2001:db8::1', '7.14.2', 'x');
    }

    public function test_prefijo_ipv6_mal_formado_lanza_excepcion(): void
    {
        $orquestador = new Ipv6DualStackCutoffOrchestrator();

        $this->expectException(\InvalidArgumentException::class);
        $orquestador->generarComandos('10.0.0.5', 'no-es-ipv6/64', '7.14.2', 'x');
    }

    public function test_comentario_con_comillas_se_escapa_en_ambos_comandos(): void
    {
        $orquestador = new Ipv6DualStackCutoffOrchestrator();

        $resultado = $orquestador->generarComandos('10.0.0.5', '2001:db8::1/48', '7.14.2', 'Cliente "Moroso"');

        $this->assertStringContainsString('comment="Cliente \\"Moroso\\""', $resultado['comando_ipv4']);
        $this->assertStringContainsString('comment="Cliente \\"Moroso\\""', $resultado['comando_ipv6']);
    }
}
