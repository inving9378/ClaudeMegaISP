<?php

namespace Tests\Unit;

use App\Pipes\DistributorsCommissionPayment;
use PHPUnit\Framework\TestCase;

class ComisionEspecialConfigTest extends TestCase
{
    private function venta(int $meses)
    {
        return new class($meses) {
            public $id = 1; public $activation_date = '2026-11-01'; public $service = 500;
            public function __construct(public int $contract_months_number) {}
        };
    }

    public function test_especial_de_un_mes_no_divide_entre_cero(): void
    {
        $c = (new DistributorsCommissionPayment)->getConfigDistributorsCommissions($this->venta(1), 100, 30, 0);
        $this->assertSame(0.0, (float) $c['total_amount_per_week']);
    }

    public function test_plazo_normal_sigue_dividiendo_entre_meses_menos_uno(): void
    {
        $c = (new DistributorsCommissionPayment)->getConfigDistributorsCommissions($this->venta(12), 100, 40, 0);
        $this->assertEqualsWithDelta((200 - 100) / 11, $c['total_amount_per_week'], 0.0001);
    }
}
