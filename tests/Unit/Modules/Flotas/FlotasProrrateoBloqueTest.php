<?php

namespace Tests\Unit\Modules\Flotas;

use App\Modules\Addons\Flotas\Services\Billing\FlotasProrrateoService as S;
use PHPUnit\Framework\TestCase;

/** Aritmética pura del cobro por bloque (#622/#623): sin BD. */
class FlotasProrrateoBloqueTest extends TestCase
{
    public function test_cinco_unidades_15_dias_y_siete_15_dias(): void
    {
        $dias = array_merge(array_fill(0, 15, 5), array_fill(0, 15, 7));
        $this->assertSame(149.00, S::costoDiario($dias));
    }

    public function test_treinta_y_dos_unidades_todo_el_mes(): void
    {
        $this->assertSame(379.00, S::costoDiario(array_fill(0, 30, 32)));
    }

    public function test_adelanto_mes_completo_y_promo_que_termina_el_15(): void
    {
        $this->assertSame(99.00, S::montoAdelantado(5, 30, 30));
        $this->assertSame(49.50, S::montoAdelantado(5, 15, 30)); // solo días desde el 16
    }

    public function test_ajuste_ejemplo_a(): void
    {
        // 5 unidades todo octubre (31 d), cobrado $99 por adelantado; el 25-oct agrega 2.
        $dias = array_merge(array_fill(0, 24, 5), array_fill(0, 7, 7));
        $this->assertSame(round(99 * 24 / 31 + 199 * 7 / 31, 2), S::costoDiario($dias));
        $this->assertSame(199.00, S::montoAdelantado(7, 31, 31)); // cargo diciembre
    }

    public function test_dias_antes_de_activarse_no_cuentan(): void
    {
        $this->assertSame(49.50, S::costoDiario(array_fill(0, 30, 5), 15));
    }
}
