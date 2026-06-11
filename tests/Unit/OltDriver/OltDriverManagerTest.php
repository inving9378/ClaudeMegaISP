<?php

namespace Tests\Unit\OltDriver;

use App\Models\Olt;
use App\Services\OltDriver\Huawei\HuaweiDriver;
use App\Services\OltDriver\OltDriverInterface;
use App\Services\OltDriver\OltDriverManager;
use App\Services\OltDriver\SmartOltDriver;
use App\Services\OltDriver\UnknownOltDriverException;
use Illuminate\Contracts\Container\Container;
use PHPUnit\Framework\TestCase;

/**
 * Pure PHPUnit — no DB, no migrations, no Laravel TestCase.
 *
 * Verifica que OltDriverManager enrute correctamente por Olt::$driver
 * y lance UnknownOltDriverException ante valores desconocidos.
 * El modelo Olt se mockea; los drivers se mockean vía OltDriverInterface.
 */
class OltDriverManagerTest extends TestCase
{
    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeOlt(string $driver): Olt
    {
        $olt = $this->createMock(Olt::class);
        // Eloquent accede a atributos vía __get → getAttribute; lo simulamos aquí.
        $olt->method('__get')->with('driver')->willReturn($driver);
        // Acceso directo a la propiedad dinámica también debe funcionar.
        $olt->driver = $driver;
        return $olt;
    }

    private function makeManager(
        OltDriverInterface $smartolt,
        OltDriverInterface $huawei,
        OltDriverInterface $default,
    ): OltDriverManager {
        $container = $this->createMock(Container::class);
        $container->method('make')->willReturnCallback(
            fn(string $abstract) => match($abstract) {
                SmartOltDriver::class    => $smartolt,
                HuaweiDriver::class      => $huawei,
                OltDriverInterface::class => $default,
                default                  => null,
            }
        );
        return new OltDriverManager($container);
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    public function test_driverFor_routes_smartolt(): void
    {
        $smartolt = $this->createMock(OltDriverInterface::class);
        $huawei   = $this->createMock(OltDriverInterface::class);
        $manager  = $this->makeManager($smartolt, $huawei, $smartolt);

        $result = $manager->driverFor($this->makeOlt(Olt::DRIVER_SMARTOLT));

        $this->assertSame($smartolt, $result);
    }

    public function test_driverFor_routes_huawei(): void
    {
        $smartolt = $this->createMock(OltDriverInterface::class);
        $huawei   = $this->createMock(OltDriverInterface::class);
        $manager  = $this->makeManager($smartolt, $huawei, $smartolt);

        $result = $manager->driverFor($this->makeOlt(Olt::DRIVER_HUAWEI));

        $this->assertSame($huawei, $result);
    }

    public function test_driverFor_throws_for_unknown_driver(): void
    {
        $smartolt = $this->createMock(OltDriverInterface::class);
        $huawei   = $this->createMock(OltDriverInterface::class);
        $manager  = $this->makeManager($smartolt, $huawei, $smartolt);

        $this->expectException(UnknownOltDriverException::class);
        $this->expectExceptionMessageMatches('/driver_inventado/');

        $manager->driverFor($this->makeOlt('driver_inventado'));
    }

    public function test_unknown_exception_message_includes_driver_name(): void
    {
        $e = new UnknownOltDriverException('foo_driver');
        $this->assertStringContainsString('foo_driver', $e->getMessage());
    }

    public function test_default_returns_global_binding(): void
    {
        $smartolt = $this->createMock(OltDriverInterface::class);
        $huawei   = $this->createMock(OltDriverInterface::class);
        $manager  = $this->makeManager($smartolt, $huawei, $smartolt);

        $result = $manager->default();

        $this->assertSame($smartolt, $result);
    }
}
