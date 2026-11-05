<?php

namespace Tests\Unit\Metricas;

use App\Models\User;
use App\Services\Metricas\EstadoServicioDedicado;
use App\Services\Metricas\FallaLectura;
use App\Services\Metricas\PoliticaMonitoreo;
use App\Services\Metricas\ProbadorLectura;
use App\Services\Metricas\SinConexionRouter;
use Illuminate\Support\Facades\DB;
use PEAR2\Net\RouterOS\DataFlowException;
use RuntimeException;

/** Item #988: «Probar lectura» no guarda lecturas y explica cada falla; #999: medir al router no dice «En línea». */
class ProbadorLecturaTest extends TablasTemporalesTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['metricas.probar.muestra_segundos' => 0.05]);
    }

    public function test_no_guarda_ninguna_lectura(): void
    {
        $servicio = $this->dedicado('Router de prueba', 'Router de prueba', 3001, ['192.0.2.10', '192.0.2.11']);
        $antes = $this->lecturasGuardadas();

        $r = app(ProbadorLectura::class)->probar($servicio, 7);

        $this->assertSame($antes, $this->lecturasGuardadas());
        $this->assertTrue($r['simulado']);
        $this->assertSame('simulado', $r['router']['host_real']);
        $this->assertSame('vlan_id', $r['interfaz']['por']);
        $this->assertCount(2, $r['pings']);
        $this->assertArrayHasKey('latencia_ms', $r['pings'][0]);
        $this->assertNotNull($r['trafico']['bajada_bps']);
        $this->assertLessThan(15000, $r['duracion_ms']);
    }

    public function test_si_contesta_el_titulo_guarda_solo_la_identidad(): void
    {
        $servicio = $this->dedicado('Router de prueba');

        $r = app(ProbadorLectura::class)->probar($servicio, 7);

        $this->assertTrue($r['identidad']['guardada_sola']);
        $this->assertFalse($r['identidad']['puede_confirmar']);
        $this->assertSame('Router de prueba', DB::table('mikrotiks')->value('identidad_esperada'));
    }

    public function test_si_no_coincide_ofrece_confirmar_y_guarda_lo_que_contesto_el_router(): void
    {
        config(['metricas.simulado.identidades' => ['Router de prueba' => 'otro-nombre']]);
        $servicio = $this->dedicado('Router de prueba');
        $usuario = new User();
        $usuario->id = 7;

        $this->assertNull(app(ProbadorLectura::class)->confirmarIdentidad($servicio, $usuario), 'sin probar antes no hay nada que confirmar');

        $r = app(ProbadorLectura::class)->probar($servicio, 7);
        $this->assertTrue($r['identidad']['puede_confirmar']);
        $this->assertSame('otro-nombre', $r['identidad']['contesto']);
        $this->assertNull(DB::table('mikrotiks')->value('identidad_esperada'));
        $this->assertSame('identidad_sin_confirmar', $r['fallas'][0]['codigo']);

        $otro = new User();
        $otro->id = 8;
        $this->assertNull(app(ProbadorLectura::class)->confirmarIdentidad($servicio, $otro), 'la prueba es de cada persona');

        $this->assertSame('otro-nombre', app(ProbadorLectura::class)->confirmarIdentidad($servicio, $usuario));
        $this->assertSame('otro-nombre', DB::table('mikrotiks')->value('identidad_esperada'));
    }

    public function test_avisa_si_la_vlan_no_existe_o_esta_repetida(): void
    {
        config(['metricas.simulado.vlan_no_encontrada' => 4094, 'metricas.simulado.vlan_repetida' => 4093]);

        $r = app(ProbadorLectura::class)->probar($this->dedicado('Router N', null, 4094), 7);
        $this->assertSame('vlan_no_encontrada', $r['fallas'][0]['codigo']);
        $this->assertFalse($r['interfaz']['encontrada']);

        $r = app(ProbadorLectura::class)->probar($this->dedicado('Router R', null, 4093), 7);
        $this->assertSame('vlan_repetida', $r['fallas'][0]['codigo']);
        $this->assertTrue($r['interfaz']['varias']);
        $this->assertCount(2, $r['interfaz']['nombres']);
    }

    public function test_causa_probable_de_cada_falla_de_conexion(): void
    {
        $conexion = fn (string $texto, ?\Throwable $previo = null) => FallaLectura::deConexion(new SinConexionRouter('192.0.2.1:8728', $texto, $previo))['codigo'];

        $this->assertSame('api_bloqueada', $conexion('Connection timed out'));
        $this->assertSame('puerto_cerrado', $conexion('Connection refused'));
        $this->assertSame('credenciales', $conexion('x', new DataFlowException('Invalid username or password supplied.', DataFlowException::CODE_INVALID_CREDENTIALS)));
        $this->assertSame('sin_conexion', $conexion('algo más'));
        $this->assertSame('sin_laboratorio', FallaLectura::deConexion(new SinConexionRouter('sin destino', 'no hay laboratorio'))['codigo']);
        $this->assertStringContainsString('Allowed Address', FallaLectura::deConexion(new RuntimeException('timed out'))['causa']);
    }

    public function test_sin_datos_de_red_lo_dice_y_no_truena(): void
    {
        $r = app(ProbadorLectura::class)->probar(999, 7);

        $this->assertFalse($r['ok']);
        $this->assertSame('sin_router', $r['fallas'][0]['codigo']);
    }

    public function test_la_ip_parece_del_router_si_es_su_ip_o_la_puerta_de_enlace_del_bloque(): void
    {
        $this->assertTrue(PoliticaMonitoreo::ipPareceDelRouter('192.0.2.1', ['192.0.2.1', null], null));
        $this->assertTrue(PoliticaMonitoreo::ipPareceDelRouter('203.0.113.9', [], '203.0.113.8/29'), 'primera IP utilizable');
        $this->assertFalse(PoliticaMonitoreo::ipPareceDelRouter('203.0.113.10', ['192.0.2.1'], '203.0.113.8/29'));
        $this->assertFalse(PoliticaMonitoreo::ipPareceDelRouter('203.0.113.9', [], '203.0.113.9/32'), 'un /32 no tiene puerta de enlace');
    }

    public function test_automatica_midiendo_al_router_no_dice_en_linea_y_revisada_si(): void
    {
        $servicio = $this->dedicado('Router de prueba', 'Router de prueba', 3001, ['192.0.2.1']);
        DB::table('servicio_monitoreo')->update(['automatica' => true]);
        DB::table('cliente_metricas')->insert(['servicio_id' => $servicio, 'cliente_id' => 1, 'ts' => now()->startOfMinute(), 'estado' => 'ok',
            'estado_ping' => 'en_linea', 'lat_avg_ms' => 0.3, 'loss_pct' => 0, 'origen' => 'simulado']);

        $e = app(EstadoServicioDedicado::class)->estado($servicio);
        $this->assertSame('revisar', $e['estado']);
        $this->assertSame('Revisa los datos de red: la IP medida parece ser del router.', $e['motivo']);
        $this->assertTrue($e['automatica']);

        DB::table('servicio_monitoreo')->update(['automatica' => false]);
        $this->assertSame('en_linea', app(EstadoServicioDedicado::class)->estado($servicio)['estado'], 'una persona ya lo revisó');
    }

    public function test_si_deja_de_ser_dedicado_su_historial_queda_en_solo_consulta(): void
    {
        $servicio = $this->dedicado();
        $cliente = (int) DB::table('client_internet_services')->where('id', $servicio)->value('client_id');
        $estado = app(EstadoServicioDedicado::class);

        $this->assertFalse($estado->resumen($cliente)['solo_consulta']);

        DB::table('client_additional_information')->update(['tipo_cliente' => 'negocio', 'is_dedicated' => false]);
        $this->assertSame([], $estado->resumen($cliente)['servicios'], 'sin lecturas no hay historial que mostrar');

        DB::table('cliente_metricas')->insert(['servicio_id' => $servicio, 'cliente_id' => $cliente, 'ts' => now()->startOfMinute(), 'estado' => 'ok', 'estado_ping' => 'en_linea']);
        $r = $estado->resumen($cliente);
        $this->assertFalse($r['is_dedicated']);
        $this->assertTrue($r['solo_consulta']);
        $this->assertFalse($r['reemplaza_pppoe'], 'se mide por PPPoE como cualquier cliente');
        $this->assertCount(1, $r['servicios']);
    }

    public function test_la_etiqueta_distingue_los_servicios_sin_vlan(): void
    {
        $servicio = $this->dedicado('Router de prueba', null, null);
        $cliente = (int) DB::table('client_internet_services')->where('id', $servicio)->value('client_id');
        $plan = DB::table('internets')->insertGetId(['title' => 'Plan X']);
        DB::table('client_internet_services')->where('id', $servicio)->update(['internet_id' => $plan]);

        $this->assertSame("#{$servicio} · Plan X", app(EstadoServicioDedicado::class)->resumen($cliente)['servicios'][0]['etiqueta']);

        DB::table('servicio_monitoreo')->update(['vlan_id' => 3001]);
        $this->assertSame('VLAN 3001 · Plan X', app(EstadoServicioDedicado::class)->resumen($cliente)['servicios'][0]['etiqueta']);
    }

    public function test_un_intento_sin_lectura_no_se_presenta_como_lectura(): void
    {
        $servicio = $this->dedicado();
        DB::table('cliente_metricas')->insert(['servicio_id' => $servicio, 'cliente_id' => 1, 'ts' => now()->startOfMinute(), 'estado' => 'router_no_verificado']);
        $this->assertTrue(app(EstadoServicioDedicado::class)->estado($servicio)['ultima_es_intento']);

        DB::table('cliente_metricas')->update(['estado' => 'ok', 'estado_ping' => 'en_linea']);
        $this->assertFalse(app(EstadoServicioDedicado::class)->estado($servicio)['ultima_es_intento']);
    }

    public function test_el_estado_en_pausa_y_la_causa_de_sin_datos(): void
    {
        $servicio = $this->dedicado();
        DB::table('metricas_colector_latidos')->insert(['ts' => now(), 'driver' => 'simulado', 'detalle' => json_encode([['router_id' => DB::table('routers')->value('id'), 'estado' => 'sin_conexion', 'error' => 'Sin conexión con 192.0.2.1']])]);

        $e = app(EstadoServicioDedicado::class)->estado($servicio);
        $this->assertSame('sin_datos', $e['estado']);
        $this->assertSame('router_sin_respuesta', $e['causa']['codigo']);
        $this->assertStringContainsString('192.0.2.1', $e['causa']['detalle']);

        DB::table('servicio_monitoreo')->update(['activo' => false, 'motivo_pausa' => 'cliente_bloqueado']);
        $e = app(EstadoServicioDedicado::class)->estado($servicio);
        $this->assertSame('pausado', $e['estado']);
        $this->assertSame('Monitoreo pausado: cliente bloqueado', $e['motivo']);
        $this->assertNull($e['causa']);
    }
}
