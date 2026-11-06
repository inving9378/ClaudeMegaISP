<?php

namespace Tests\Unit\Services;

use App\Services\Finance\Billing\ReglasFacturacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\TestCase as LaravelTestCase;
use Tests\CreatesApplication;

/**
 * #1090: el interruptor BILLING_NO_CARGAR_MES_BLOQUEADO. Apagado = nunca omite; encendido = omite solo
 * a Bloqueados. La consulta al estado se simula: no toca ninguna base.
 */
class NoCargarMesBloqueadoTest extends LaravelTestCase
{
    use CreatesApplication;

    private function conEstado(?string $estado): void
    {
        DB::shouldReceive('table')->with('client_main_information')->andReturn(
            \Mockery::mock()->shouldReceive('where')->andReturnSelf()->shouldReceive('value')->andReturn($estado)->getMock()
        );
    }

    public function test_apagado_por_defecto_nunca_omite(): void
    {
        $this->assertFalse((bool) config('billing.no_cargar_mes_bloqueado'));
        $this->conEstado(ReglasFacturacion::BLOQUEADO);
        $this->assertFalse(ReglasFacturacion::omiteCargoPorBloqueo((object) ['id' => 1]));
    }

    public function test_encendido_omite_solo_a_bloqueados(): void
    {
        config(['billing.no_cargar_mes_bloqueado' => true]);
        $this->conEstado(ReglasFacturacion::BLOQUEADO);
        $this->assertTrue(ReglasFacturacion::omiteCargoPorBloqueo((object) ['id' => 1]));
    }

    public function test_encendido_no_omite_a_activos(): void
    {
        config(['billing.no_cargar_mes_bloqueado' => true]);
        $this->conEstado(ReglasFacturacion::ACTIVO);
        $this->assertFalse(ReglasFacturacion::omiteCargoPorBloqueo((object) ['id' => 1]));
    }
}
