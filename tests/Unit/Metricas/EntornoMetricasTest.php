<?php

namespace Tests\Unit\Metricas;

use App\Services\Metricas\EntornoMetricas;
use App\Services\Metricas\MikrotikMetricasDriver;
use PHPUnit\Framework\TestCase;

/**
 * Item #988: a qué router se conecta el colector según el entorno. Es lo que evita que producción
 * vaya a otro router por un APP_ENV mal puesto, y que DEV llegue a un router real.
 */
class EntornoMetricasTest extends TestCase
{
    private const REGISTRADO = ['ip' => '192.0.2.1', 'login' => 'api', 'password' => 'x', 'port' => 8728];

    private const LABORATORIO = ['host' => '198.51.100.7', 'usuario' => 'lab', 'password' => 'y', 'puerto' => 8735];

    public function test_es_produccion_por_app_env_o_por_la_base(): void
    {
        $lista = ['base_prod'];

        $this->assertTrue(EntornoMetricas::decidir('production', 'otra', $lista));
        $this->assertTrue(EntornoMetricas::decidir('local', 'base_prod', $lista), 'APP_ENV=local con la base de producción');
        $this->assertFalse(EntornoMetricas::decidir('local', 'otra', $lista));
        $this->assertFalse(EntornoMetricas::decidir('local', 'BASE_PROD', $lista), 'el nombre de la base es exacto');
    }

    public function test_en_produccion_va_al_router_registrado_aunque_haya_laboratorio(): void
    {
        $this->assertSame(self::REGISTRADO, MikrotikMetricasDriver::destino(true, self::REGISTRADO, self::LABORATORIO));
    }

    public function test_fuera_de_produccion_va_al_laboratorio(): void
    {
        $destino = MikrotikMetricasDriver::destino(false, self::REGISTRADO, self::LABORATORIO);

        $this->assertSame('198.51.100.7', $destino['ip']);
        $this->assertSame('lab', $destino['login']);
        $this->assertSame(8735, $destino['port']);
    }

    public function test_fuera_de_produccion_sin_laboratorio_no_se_conecta_a_nadie(): void
    {
        $this->assertNull(MikrotikMetricasDriver::destino(false, self::REGISTRADO, []));
        $this->assertNull(MikrotikMetricasDriver::destino(false, self::REGISTRADO, ['host' => '198.51.100.7', 'usuario' => 'lab', 'password' => '']));
        $this->assertNull(MikrotikMetricasDriver::destino(false, self::REGISTRADO, ['host' => ' ', 'usuario' => 'lab', 'password' => 'y']));
    }
}
