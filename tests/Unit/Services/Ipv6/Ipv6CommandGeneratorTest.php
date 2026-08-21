<?php

namespace Tests\Unit\Services\Ipv6;

use App\Services\Ipv6\Drivers\FallbackDriver;
use App\Services\Ipv6\Drivers\RouterOs6xDriver;
use App\Services\Ipv6\Drivers\RouterOs70Driver;
use App\Services\Ipv6\Drivers\RouterOs713Driver;
use App\Services\Ipv6\Ipv6AddressPlanCalculator;
use App\Services\Ipv6\Ipv6CommandDriverFactory;
use App\Services\Ipv6\Ipv6CommandGenerator;
use PHPUnit\Framework\TestCase; // TestCase PURO de PHPUnit: NO toca BD, NO migrate:fresh.

class Ipv6CommandGeneratorTest extends TestCase
{
    private function plan(): array
    {
        return Ipv6AddressPlanCalculator::calcular('2001:db8::/48', 10, 1.5, ['distrito-norte']);
    }

    // --- Factory: resolución de versión -> familia -------------------------------

    public function test_factory_resuelve_6x(): void
    {
        $this->assertInstanceOf(RouterOs6xDriver::class, Ipv6CommandDriverFactory::paraVersion('6.49.6'));
    }

    public function test_factory_resuelve_7_0_a_7_12(): void
    {
        $this->assertInstanceOf(RouterOs70Driver::class, Ipv6CommandDriverFactory::paraVersion('7.6'));
        $this->assertInstanceOf(RouterOs70Driver::class, Ipv6CommandDriverFactory::paraVersion('7.12.1'));
    }

    public function test_factory_resuelve_7_13_mas(): void
    {
        $this->assertInstanceOf(RouterOs713Driver::class, Ipv6CommandDriverFactory::paraVersion('7.13'));
        $this->assertInstanceOf(RouterOs713Driver::class, Ipv6CommandDriverFactory::paraVersion('7.14.2'));
    }

    public function test_factory_version_no_reconocida_cae_a_fallback(): void
    {
        $this->assertInstanceOf(FallbackDriver::class, Ipv6CommandDriverFactory::paraVersion('9.9'));
        $this->assertInstanceOf(FallbackDriver::class, Ipv6CommandDriverFactory::paraVersion('no-es-una-version'));
    }

    // --- Mismo plan contra cada driver produce la salida esperada de su familia ---

    public function test_mismo_plan_produce_comandos_en_los_tres_drivers_reales(): void
    {
        $plan = $this->plan();
        $gen = new Ipv6CommandGenerator();

        foreach (['6.49.6', '7.6', '7.14.2'] as $version) {
            $resultado = $gen->generarParaPlan($plan, $version);
            $this->assertNotEmpty($resultado['comandos'], "Versión {$version} debe producir comandos.");
            $this->assertSame([], $resultado['advertencias'], "Versión {$version} no debe traer advertencias en el flujo normal.");
            $this->assertStringContainsString('/ipv6 route add dst-address=2001:db8::/48 type=blackhole', $resultado['comandos'][0]);
            $this->assertStringContainsString('/ipv6 pool add name=pool-distrito-norte', implode("\n", $resultado['comandos']));
        }
    }

    public function test_capacidad_ausente_produce_advertencia_no_comando(): void
    {
        $plan = $this->plan();
        $gen = new Ipv6CommandGenerator();

        $resultado = $gen->generarParaPlan($plan, 'version-invalida');

        $this->assertSame([], $resultado['comandos'], 'Versión no reconocida jamás debe emitir un comando.');
        $this->assertNotEmpty($resultado['advertencias']);
        foreach ($resultado['advertencias'] as $advertencia) {
            $this->assertStringContainsString('no reconocida', $advertencia);
        }
    }

    // --- Diferencia documentada: eliminarPorMarcador en 7.13+ (incidente #811) ---

    public function test_eliminar_por_marcador_forma_directa_en_versiones_viejas(): void
    {
        $gen = new Ipv6CommandGenerator();

        foreach (['6.49.6', '7.12.1'] as $version) {
            $resultado = $gen->generarEliminarPorMarcador('MgNet-IPv6', $version);
            $this->assertSame(
                ['/ipv6 address remove [find comment~"MgNet-IPv6"]'],
                $resultado['comandos']
            );
            $this->assertSame([], $resultado['advertencias']);
        }
    }

    public function test_eliminar_por_marcador_en_7_13_evita_entradas_dinamicas_y_advierte(): void
    {
        $gen = new Ipv6CommandGenerator();
        $resultado = $gen->generarEliminarPorMarcador('MgNet-IPv6', '7.14.2');

        $comandos = implode("\n", $resultado['comandos']);
        $this->assertStringContainsString(':foreach', $comandos);
        $this->assertStringContainsString('dynamic', $comandos);
        $this->assertStringNotContainsString('/ipv6 address remove [find comment~', $comandos);
        $this->assertNotEmpty($resultado['advertencias']);
        $this->assertStringContainsString('entradas dinámicas', $resultado['advertencias'][0]);
    }

    // --- Capacidades declaradas -----------------------------------------------

    public function test_fallback_driver_declara_todas_las_capacidades_en_falso(): void
    {
        $driver = new FallbackDriver('9.9');
        foreach ($driver->capacidades() as $soportada) {
            $this->assertFalse($soportada);
        }
    }

    public function test_drivers_reales_declaran_todas_las_capacidades_en_verdadero(): void
    {
        foreach ([new RouterOs6xDriver(), new RouterOs70Driver(), new RouterOs713Driver()] as $driver) {
            foreach ($driver->capacidades() as $soportada) {
                $this->assertTrue($soportada, $driver->nombre());
            }
        }
    }
}
