<?php

namespace Tests\Unit\Modules\Clientes;

use App\Modules\Core\Clientes\Services\GraciaRecurrenteCalculator as G;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/** Gracia y cobro sin saldo de recurrentes (2026-10-14). PHPUnit puro, sin BD. */
class GraciaRecurrenteCalculatorTest extends TestCase
{
    private function hoy(): Carbon
    {
        return Carbon::parse('2026-10-14 10:00:00');
    }

    public function test_gracia_solo_con_contrato_cumplido(): void
    {
        $this->assertFalse(G::aplicaGracia(10));
        $this->assertFalse(G::aplicaGracia(1));
        $this->assertTrue(G::aplicaGracia(0));
    }

    public function test_dentro_del_contrato_cobra_hasta_que_la_deuda_alcanza_los_meses_restantes(): void
    {
        // faltan 3 meses de contrato: con 0, 1 y 2 de deuda sigue cobrando; con 3 ya no.
        foreach ([0, 1, 2] as $deuda) {
            $this->assertTrue(G::puedeCobrarSinSaldo(3, $deuda, null, $this->hoy()), "deuda $deuda");
        }
        $this->assertFalse(G::puedeCobrarSinSaldo(3, 3, null, $this->hoy()));
        $this->assertFalse(G::puedeCobrarSinSaldo(3, 4, null, $this->hoy()));
    }

    public function test_dentro_del_contrato_la_gracia_vencida_no_detiene_el_cobro(): void
    {
        // lo que manda dentro del contrato es el tope de deuda, no la gracia heredada
        $vencida = Carbon::parse('2026-09-01');
        $this->assertTrue(G::puedeCobrarSinSaldo(5, 1, $vencida, $this->hoy()));
    }

    public function test_contrato_cumplido_sin_gracia_cobra_y_la_inicia(): void
    {
        $this->assertTrue(G::puedeCobrarSinSaldo(0, 0, null, $this->hoy()));
    }

    public function test_contrato_cumplido_con_gracia_vigente_sigue_cobrando(): void
    {
        $this->assertTrue(G::puedeCobrarSinSaldo(0, 2, Carbon::parse('2026-12-01'), $this->hoy()));
    }

    public function test_contrato_cumplido_con_gracia_vencida_deja_de_cobrar(): void
    {
        $this->assertFalse(G::puedeCobrarSinSaldo(0, 3, Carbon::parse('2026-10-13 23:59:59'), $this->hoy()));
    }

    public function test_dias_de_gracia_son_90(): void
    {
        $this->assertSame(90, G::DIAS_GRACIA);
    }
}
