<?php

namespace Tests\Unit\Metricas;

use App\Models\ClientAdditionalInformation;
use App\Observers\MonitoreoDedicadoObserver;
use App\Services\Metricas\AsegurarMonitoreoDedicados;
use App\Services\Metricas\DatosRedDedicado;
use App\Services\Metricas\PoliticaMonitoreo;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Item #988: el monitoreo es parte del servicio dedicado; se crea, se pausa y se reanuda solo. */
class AsegurarMonitoreoTest extends TablasTemporalesTestCase
{
    private int $cliente;

    private int $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-04 10:00:00');
        $router = DB::table('routers')->insertGetId(['title' => 'Router de prueba', 'ip_host' => '192.0.2.1']);
        DB::table('mikrotiks')->insert(['router_id' => $router, 'login_api' => 'api', 'password_api' => 'x', 'port_api' => 8728]);
        $red = DB::table('networks')->insertGetId(['network' => '203.0.113.8', 'bm' => 29]);
        $ip = DB::table('network_ips')->insertGetId(['ip' => '203.0.113.10', 'network_id' => $red]);
        $this->cliente = DB::table('clients')->insertGetId([]);
        DB::table('client_main_information')->insert(['client_id' => $this->cliente, 'estado' => 'Activo']);
        DB::table('client_additional_information')->insert(['client_id' => $this->cliente, 'tipo_cliente' => 'dedicado', 'is_dedicated' => true]);
        $this->servicio = DB::table('client_internet_services')->insertGetId([
            'client_id' => $this->cliente, 'estado' => 'Activo', 'router_id' => $router, 'ipv4' => (string) $ip,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_alta_de_dedicado_se_crea_sola_sin_revisar_y_se_mide(): void
    {
        $this->artisan('metricas:colectar')->assertExitCode(0);

        $config = DB::table('servicio_monitoreo')->where('servicio_id', $this->servicio)->first();
        $this->assertTrue((bool) $config->automatica);
        $this->assertTrue((bool) $config->activo);
        $this->assertSame('203.0.113.8/29', $config->bloque_cidr);
        $this->assertSame(['203.0.113.10'], DB::table('servicio_monitoreo_ips')->pluck('ip')->all());
        $this->assertSame(1, DB::table('cliente_metricas')->where('servicio_id', $this->servicio)->count());
        $this->assertSame(1, DB::table('cliente_metricas_ping')->where('ip', '203.0.113.10')->count());
    }

    public function test_si_la_red_es_muy_grande_el_bloque_es_la_ip_sola(): void
    {
        DB::table('networks')->update(['network' => '203.0.112.0', 'bm' => 21]);

        app(AsegurarMonitoreoDedicados::class)->todos();

        $this->assertSame('203.0.113.10/32', DB::table('servicio_monitoreo')->value('bloque_cidr'));
    }

    public function test_bloquear_pausa_con_motivo_y_desbloquear_reanuda(): void
    {
        $this->artisan('metricas:colectar')->assertExitCode(0);

        $this->estadoCliente('Bloqueado');
        Carbon::setTestNow('2026-11-04 10:01:00');
        $this->artisan('metricas:colectar')->assertExitCode(0);

        $this->assertFalse((bool) DB::table('servicio_monitoreo')->value('activo'));
        $this->assertSame('cliente_bloqueado', DB::table('servicio_monitoreo')->value('motivo_pausa'));
        $this->assertSame(1, DB::table('cliente_metricas')->count(), 'en pausa no se mide');
        $pausa = DB::table('servicio_monitoreo_pausas')->first();
        $this->assertSame('cliente_bloqueado', $pausa->motivo);
        $this->assertNull($pausa->hasta);

        $this->estadoCliente('Activo');
        Carbon::setTestNow('2026-11-04 10:05:00');
        $this->artisan('metricas:colectar')->assertExitCode(0);

        $this->assertTrue((bool) DB::table('servicio_monitoreo')->value('activo'));
        $this->assertNull(DB::table('servicio_monitoreo')->value('motivo_pausa'));
        $this->assertSame('2026-11-04 10:05:00', DB::table('servicio_monitoreo_pausas')->value('hasta'));
        $this->assertSame(2, DB::table('cliente_metricas')->count(), 'volvió a medir');
    }

    public function test_cancelar_pausa_y_reactivar_reanuda_conservando_lo_medido(): void
    {
        $this->artisan('metricas:colectar')->assertExitCode(0);

        $this->estadoCliente('Cancelado');
        app(AsegurarMonitoreoDedicados::class)->todos();
        $this->assertSame('cliente_cancelado', DB::table('servicio_monitoreo')->value('motivo_pausa'));
        $this->assertSame(1, DB::table('cliente_metricas')->count(), 'lo medido se conserva');

        $this->estadoCliente('Activo');
        app(AsegurarMonitoreoDedicados::class)->todos();
        $this->assertTrue((bool) DB::table('servicio_monitoreo')->value('activo'));
    }

    public function test_servicio_pendiente_no_mide_y_activado_si(): void
    {
        DB::table('client_internet_services')->update(['estado' => 'Pendiente']);
        $this->artisan('metricas:colectar')->assertExitCode(0);

        $this->assertSame('servicio_pendiente', DB::table('servicio_monitoreo')->value('motivo_pausa'));
        $this->assertSame(0, DB::table('cliente_metricas')->count());

        DB::table('client_internet_services')->update(['estado' => 'Activado']);
        Carbon::setTestNow('2026-11-04 10:01:00');
        $this->artisan('metricas:colectar')->assertExitCode(0);

        $this->assertNull(DB::table('servicio_monitoreo')->value('motivo_pausa'));
        $this->assertSame(1, DB::table('cliente_metricas')->count());
    }

    public function test_poner_tipo_dedicado_a_un_cliente_existente_la_crea_sola_y_quitarlo_pausa(): void
    {
        DB::table('client_additional_information')->update(['tipo_cliente' => 'residencial']);
        app(AsegurarMonitoreoDedicados::class)->todos();
        $this->assertSame(0, DB::table('servicio_monitoreo')->count(), 'sin el tipo no se crea');

        DB::table('client_additional_information')->update(['tipo_cliente' => 'dedicado']);
        $this->evento();
        $this->assertSame(1, DB::table('servicio_monitoreo')->count());
        $this->assertTrue((bool) DB::table('servicio_monitoreo')->value('activo'));

        DB::table('client_additional_information')->update(['tipo_cliente' => 'residencial']);
        $this->evento();
        $this->assertSame('no_dedicado', DB::table('servicio_monitoreo')->value('motivo_pausa'));
        $this->assertSame('ya no es cliente dedicado', PoliticaMonitoreo::texto('no_dedicado'));
    }

    public function test_la_tarea_diaria_crea_faltantes_y_no_duplica(): void
    {
        $this->artisan('metricas:asegurar-dedicados')->assertExitCode(0);
        $this->estadoCliente('Bloqueado');
        $this->artisan('metricas:asegurar-dedicados')->assertExitCode(0);
        $this->artisan('metricas:asegurar-dedicados')->assertExitCode(0);

        $this->assertSame(1, DB::table('servicio_monitoreo')->count());
        $this->assertSame(1, DB::table('servicio_monitoreo_ips')->count());
        $this->assertSame(1, DB::table('servicio_monitoreo_pausas')->count());
    }

    public function test_un_estado_que_no_se_conoce_pausa(): void
    {
        $this->assertSame('cliente_otro', PoliticaMonitoreo::motivoPausa(true, false, 'Otro', 'Activo'));
        $this->assertSame('servicio_otro', PoliticaMonitoreo::motivoPausa(true, false, 'Activo', null));
        $this->assertSame('cliente_eliminado', PoliticaMonitoreo::motivoPausa(true, true, 'Activo', 'Activo'));
        $this->assertNull(PoliticaMonitoreo::motivoPausa(true, false, 'Activo', 'Activo'));
    }

    public function test_guardar_datos_de_red_la_marca_revisada_y_el_estado_no_se_cambia_a_mano(): void
    {
        app(AsegurarMonitoreoDedicados::class)->todos();
        $this->estadoCliente('Bloqueado');
        $router = DB::table('routers')->value('id');

        $r = app(DatosRedDedicado::class)->guardar($this->servicio, [
            'router_id' => $router, 'vlan_id' => 3001, 'bloque_cidr' => '203.0.113.8/29',
            'activo' => true, 'ips' => [['ip' => '203.0.113.11', 'monitorear' => true]],
        ], 7);

        $config = DB::table('servicio_monitoreo')->first();
        $this->assertFalse((bool) $config->automatica);
        $this->assertSame(7, (int) $config->revisado_por);
        $this->assertFalse((bool) $config->activo, 'el «activo» que llegue no manda: el cliente está bloqueado');
        $this->assertFalse($r['monitoreo']['activo']);
        $this->assertSame('cliente bloqueado', $r['monitoreo']['motivo']);
    }

    private function estadoCliente(string $estado): void
    {
        DB::table('client_main_information')->update(['estado' => $estado]);
    }

    private function evento(): void
    {
        $modelo = new ClientAdditionalInformation();
        $modelo->client_id = $this->cliente;
        $modelo->wasRecentlyCreated = true;
        (new MonitoreoDedicadoObserver())->saved($modelo);
    }
}
