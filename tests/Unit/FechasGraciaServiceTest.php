<?php

namespace Tests\Unit;

use App\Services\Billing\FechasGraciaService;
use PHPUnit\Framework\TestCase;

class FechasGraciaServiceTest extends TestCase
{
    public function test_dia_31_en_mes_de_30_se_limita(): void
    {
        $this->assertSame('2026-11-30', FechasGraciaService::diaFacturacion(2026, 11, 31)->toDateString());
    }

    public function test_dia_30_en_febrero_bisiesto_y_normal(): void
    {
        $this->assertSame('2028-02-29', FechasGraciaService::diaFacturacion(2028, 2, 30)->toDateString());
        $this->assertSame('2027-02-28', FechasGraciaService::diaFacturacion(2027, 2, 31)->toDateString());
    }

    public function test_gracia_cero_cae_el_mismo_dia(): void
    {
        $f = FechasGraciaService::diaFacturacion(2026, 11, 15);
        $this->assertSame('2026-11-15', FechasGraciaService::finGracia($f, 0)->toDateString());
        $this->assertSame('2026-11-20', FechasGraciaService::finGracia($f, 5)->toDateString());
    }
}
