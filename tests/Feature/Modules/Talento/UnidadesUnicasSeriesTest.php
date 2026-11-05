<?php

namespace Tests\Feature\Modules\Talento;

use App\Modules\Addons\Talento\Services\DashboardService;
use Tests\TestCase;

/** UNI-T H1 fase 2: con el interruptor apagado la serie diaria no cambia de forma. */
class UnidadesUnicasSeriesTest extends TestCase
{
    public function test_serie_diaria_conserva_forma_con_interruptor_apagado_y_encendido(): void
    {
        foreach ([false, true] as $flag) {
            config(['unificacion.interruptores.unidades_unicas' => $flag]);
            $s = app(DashboardService::class)->dailyProduction(7);
            $this->assertCount(7, $s['categories']);
            $this->assertCount(7, $s['instalaciones']);
            $this->assertCount(7, $s['garantias_billable']);
        }
    }
}
