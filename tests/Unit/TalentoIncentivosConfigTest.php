<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class TalentoIncentivosConfigTest extends TestCase
{
    public function test_defaults_reproducen_el_comportamiento_actual(): void
    {
        $c = require __DIR__ . '/../../config/talento_incentivos.php';

        $this->assertFalse($c['periodo']['vendedores_usa_payweek']);
        $this->assertSame('compatible', $c['compatibilidad']['vendedor_talento']);
        $this->assertSame([300, 15, 120], [$c['sueldo_vendedor']['por_venta'], $c['sueldo_vendedor']['por_prospecto'], $c['sueldo_vendedor']['atajo']]);
        $this->assertFalse($c['sueldo_vendedor']['desde_regla_bd']);
        $this->assertSame('unidad_completa', $c['participantes']['modo']);
        $this->assertFalse($c['garantias']['cero_puntos_propia_instalacion']);
        $this->assertFalse($c['garantias']['clawback']);
        $this->assertFalse($c['metas']['fuente_unica_cuota_regla']);
        $this->assertFalse($c['evaluacion_planta_externa']['activo']);
    }
}
