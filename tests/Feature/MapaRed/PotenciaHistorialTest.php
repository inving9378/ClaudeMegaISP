<?php

namespace Tests\Feature\MapaRed;

use App\Modules\Addons\MapaRed\Models\MapaRedLayer;
use App\Modules\Addons\MapaRed\Models\MapaRedPotenciaLectura;
use App\Modules\Addons\MapaRed\Services\MapaRedNapHealthService;
use App\Modules\Addons\MapaRed\Services\MapaRedPotenciaHistorialService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Item #1270 (MR-33) — captura, cambio mínimo, retención y serie de potencia por caja (datos sintéticos). */
class PotenciaHistorialTest extends TestCase
{
    use DatabaseTransactions;

    private function servicio(?float $minima): MapaRedPotenciaHistorialService
    {
        $salud = $this->createMock(MapaRedNapHealthService::class);
        $salud->method('calcular')->willReturn([
            'potencia_minima_dbm' => $minima, 'potencia_promedio_dbm' => $minima === null ? null : $minima + 1, 'total_onus' => 3,
        ]);

        return new MapaRedPotenciaHistorialService($salud);
    }

    private function lectura(int $layer, float $min, Carbon $cuando): void
    {
        MapaRedPotenciaLectura::create(['layer_id' => $layer, 'potencia_minima_dbm' => $min, 'total_onus' => 3, 'capturada_en' => $cuando]);
    }

    public function test_captura_primera_una_al_dia_y_solo_si_cambia(): void
    {
        $dia1 = Carbon::parse('2030-01-10 04:30');
        $this->assertSame('guardada', $this->servicio(-20.0)->capturar(990001, $dia1));
        $this->assertSame('ya_hoy', $this->servicio(-25.0)->capturar(990001, $dia1->copy()->addHours(3)));
        $this->assertSame('sin_cambio', $this->servicio(-20.3)->capturar(990001, $dia1->copy()->addDay()));
        $this->assertSame('guardada', $this->servicio(-21.0)->capturar(990001, $dia1->copy()->addDays(2)));
        $this->assertSame('sin_lectura', $this->servicio(null)->capturar(990002, $dia1));
        $this->assertSame(2, MapaRedPotenciaLectura::where('layer_id', 990001)->count());
    }

    public function test_retencion_borra_solo_lo_de_mas_de_12_meses(): void
    {
        $hoy = Carbon::parse('2031-06-15');
        $this->lectura(990003, -20, $hoy->copy()->subMonths(13));
        $this->lectura(990003, -21, $hoy->copy()->subMonths(11));
        $this->assertSame(1, $this->servicio(-20.0)->purgar($hoy));
        $this->assertSame(1, MapaRedPotenciaLectura::where('layer_id', 990003)->count());
    }

    public function test_serie_muestra_curva_y_cuanto_bajo_desde_la_instalacion(): void
    {
        $layer = MapaRedLayer::create(['type' => 'marker', 'route' => '', 'dialog' => '', 'icon' => '', 'label' => '', 'coords' => [], 'data' => [], 'text' => 'Caja sintética', 'potencia_instalacion_dbm' => -19.0]);
        $hoy = Carbon::parse('2032-03-01');
        $this->lectura($layer->id, -19.5, $hoy->copy()->subDays(60));
        $this->lectura($layer->id, -20.0, $hoy->copy()->subDays(20));
        $this->lectura($layer->id, -22.5, $hoy->copy()->subDays(2));

        $s30 = $this->servicio(null)->serie($layer->id, 30, $hoy);
        $this->assertCount(2, $s30['puntos']);
        $this->assertSame(3.5, $s30['bajo_db']);
        $this->assertCount(3, $this->servicio(null)->serie($layer->id, 90, $hoy)['puntos']);
    }
}
