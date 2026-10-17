<?php

namespace Tests\Unit\Services;

use App\Services\Finance\Billing\IndicadoresFacturacionService as I;
use PHPUnit\Framework\TestCase;

/** FAC-11 (item #519): sin denominador no hay porcentaje — «Sin datos», nunca 0% inventado. */
class IndicadoresFacturacionPorcentajeTest extends TestCase
{
    public function test_porcentaje(): void
    {
        $this->assertSame('50.0%', I::porcentaje(1, 2));
        $this->assertSame('0.0%', I::porcentaje(0, 917));
        $this->assertSame('100.0%', I::porcentaje(3, 3));
        $this->assertSame(I::SIN_DATOS, I::porcentaje(0, 0));
    }
}
