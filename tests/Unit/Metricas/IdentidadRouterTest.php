<?php

namespace Tests\Unit\Metricas;

use App\Services\Metricas\IdentidadRouter;
use Illuminate\Support\Facades\DB;

/** Item #988: qué pasa según la identidad que conteste el router. */
class IdentidadRouterTest extends TablasTemporalesTestCase
{
    public function test_evaluar(): void
    {
        $this->assertSame(IdentidadRouter::COINCIDE, IdentidadRouter::evaluar('R1', 'R1', 'otro'));
        $this->assertSame(IdentidadRouter::DISTINTA, IdentidadRouter::evaluar('R2', 'R1', 'R2'), 'registrada manda sobre el título');
        $this->assertSame(IdentidadRouter::POR_TITULO, IdentidadRouter::evaluar('R1', null, 'R1'));
        $this->assertSame(IdentidadRouter::POR_TITULO, IdentidadRouter::evaluar('R1', '', ' R1 '));
        $this->assertSame(IdentidadRouter::SIN_REGISTRAR, IdentidadRouter::evaluar('r1', null, 'R1'), 'el título es exacto');
        $this->assertSame(IdentidadRouter::SIN_REGISTRAR, IdentidadRouter::evaluar('', null, ''));
    }

    public function test_si_contesta_el_titulo_se_guarda_sola_y_se_lee(): void
    {
        $servicio = $this->dedicado('Router de prueba');

        $this->artisan('metricas:colectar')->assertExitCode(0);

        $this->assertSame('Router de prueba', DB::table('mikrotiks')->value('identidad_esperada'));
        $this->assertNotSame('router_no_verificado', DB::table('cliente_metricas')->where('servicio_id', $servicio)->value('estado'));
        $this->assertSame(1, DB::table('cliente_metricas_contadores')->count());
    }

    public function test_si_no_coincide_con_el_titulo_no_se_guarda_ni_se_lee(): void
    {
        config(['metricas.simulado.identidades' => ['Router de prueba' => 'otro-nombre']]);
        $servicio = $this->dedicado('Router de prueba');

        $this->artisan('metricas:colectar')->assertExitCode(0);

        $this->assertNull(DB::table('mikrotiks')->value('identidad_esperada'));
        $this->assertSame('router_no_verificado', DB::table('cliente_metricas')->where('servicio_id', $servicio)->value('estado'));
        $this->assertSame(0, DB::table('cliente_metricas_contadores')->count());
    }

    public function test_si_la_identidad_cambia_despues_deja_de_leer_y_lo_registra(): void
    {
        config(['metricas.simulado.identidades' => ['Router de prueba' => 'cambiada']]);
        $servicio = $this->dedicado('Router de prueba', 'original');

        $this->artisan('metricas:colectar')->assertExitCode(0);

        $this->assertSame('original', DB::table('mikrotiks')->value('identidad_esperada'), 'no se reemplaza sola');
        $this->assertSame('router_no_verificado', DB::table('cliente_metricas')->where('servicio_id', $servicio)->value('estado'));
        $detalle = json_decode(DB::table('metricas_colector_latidos')->value('detalle'), true)[0];
        $this->assertSame('identidad_distinta', $detalle['estado']);
        $this->assertStringContainsString('cambiada', $detalle['error']);
    }

    public function test_el_dry_run_no_guarda_la_identidad(): void
    {
        $this->dedicado('Router de prueba');

        $this->artisan('metricas:colectar', ['--dry-run' => true])->assertExitCode(0);

        $this->assertNull(DB::table('mikrotiks')->value('identidad_esperada'));
        $this->assertSame(0, DB::table('cliente_metricas')->count());
    }

    public function test_vlan_repetida_y_no_encontrada_quedan_distinguidas(): void
    {
        config(['metricas.simulado.vlan_repetida' => 4093, 'metricas.simulado.vlan_no_encontrada' => 4094]);
        $repetida = $this->dedicado('Router R', null, 4093);
        $noExiste = $this->dedicado('Router N', null, 4094);

        $this->artisan('metricas:colectar')->assertExitCode(0);

        $this->assertSame('vlan_repetida', DB::table('cliente_metricas')->where('servicio_id', $repetida)->value('estado'));
        $this->assertSame('interfaz_no_encontrada', DB::table('cliente_metricas')->where('servicio_id', $noExiste)->value('estado'));
    }
}
