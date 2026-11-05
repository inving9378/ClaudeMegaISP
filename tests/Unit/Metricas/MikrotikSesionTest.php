<?php

namespace Tests\Unit\Metricas;

use App\Services\Metricas\DeltaContadores;
use App\Services\Metricas\LecturaInterfaz;
use App\Services\Metricas\MikrotikSesion;
use PHPUnit\Framework\TestCase;

/**
 * Item #988: lecturas del MikroTik con fixtures de RouterOS 7, sin router real ni MySQL.
 */
class MikrotikSesionTest extends TestCase
{
    private const INTERFAZ_A = 'VLAN 101 CLIENTE A';
    private const INTERFAZ_B = 'VLAN 100 CLIENTE B';

    private FixtureRouterOsApi $api;
    private MikrotikSesion $sesion;

    protected function setUp(): void
    {
        $this->api = new FixtureRouterOsApi('routeros7-ejemplo.json');
        $this->sesion = new MikrotikSesion($this->api, '198.51.100.1:8730', 'monitoreo', true);
    }

    public function test_identidad_del_router(): void
    {
        $this->assertSame('ROUTER DE PRUEBA', $this->sesion->identidad());
    }

    public function test_varias_interfaces_con_espacios_en_una_sola_lectura(): void
    {
        $l = $this->sesion->interfaces([
            '1001' => ['interfaz' => self::INTERFAZ_A, 'vlan_id' => 101],
            '1002' => ['interfaz' => self::INTERFAZ_B, 'vlan_id' => 100],
        ]);

        $this->assertInstanceOf(LecturaInterfaz::class, $l['1001']);
        $this->assertSame(1834567890123, $l['1001']->txByte);
        $this->assertSame(912345678901, $l['1001']->rxByte);
        $this->assertTrue($l['1001']->running);
        $this->assertSame(98765432109, $l['1002']->txByte);
        $this->assertSame(1, $this->api->llamadas['/interface/print']);
        $this->assertArrayNotHasKey('/interface/vlan/print', $this->api->llamadas);
    }

    public function test_sin_nombre_se_busca_por_vlan_id(): void
    {
        $l = $this->sesion->interfaces(['x' => ['interfaz' => null, 'vlan_id' => 101]]);

        $this->assertSame(1834567890123, $l['x']?->txByte);
    }

    public function test_nombre_que_el_router_ya_no_tiene_cae_al_vlan_id(): void
    {
        $l = $this->sesion->interfaces(['x' => ['interfaz' => 'VLAN 101 nombre viejo', 'vlan_id' => 101]]);

        $this->assertSame(1834567890123, $l['x']?->txByte);
    }

    public function test_vlan_id_en_dos_interfaces_no_se_adivina(): void
    {
        $l = $this->sesion->interfaces(['x' => ['interfaz' => null, 'vlan_id' => 102]]);

        $this->assertNull($l['x']);
        $this->assertSame('vlan_id', $this->sesion->resolucion()['x']['por']);
        $this->assertCount(2, $this->sesion->resolucion()['x']['nombres'], 'se puede avisar que está repetida');
    }

    public function test_interfaz_que_no_existe(): void
    {
        $l = $this->sesion->interfaces(['x' => ['interfaz' => 'VLAN 999 NO EXISTE', 'vlan_id' => null]]);

        $this->assertNull($l['x']);
    }

    public function test_interfaz_deshabilitada_no_cuenta_como_arriba(): void
    {
        $l = $this->sesion->interfaces(['x' => ['interfaz' => 'VLAN 102 RESERVA', 'vlan_id' => null]]);

        $this->assertFalse($l['x']->running);
    }

    public function test_de_dos_lecturas_bajada_es_tx_y_subida_es_rx(): void
    {
        $antes = $this->sesion->interfaces(['x' => ['interfaz' => self::INTERFAZ_A, 'vlan_id' => 101]])['x'];
        $ahora = new LecturaInterfaz($antes->rxByte + 3_750_000, $antes->txByte + 37_500_000, $antes->rxPacket + 6000, $antes->txPacket + 30000, true, $antes->leidoEn + 60);

        $f = DeltaContadores::calcular($ahora, [
            'rx_byte' => $antes->rxByte, 'tx_byte' => $antes->txByte, 'rx_packet' => $antes->rxPacket,
            'tx_packet' => $antes->txPacket, 'leido_en' => $antes->leidoEn,
        ], 300, 40_000_000_000);

        $this->assertSame(5_000_000, $f['bajada_bps']);
        $this->assertSame(500_000, $f['subida_bps']);
    }

    public function test_ping_con_tiempos_en_microsegundos(): void
    {
        $p = $this->sesion->ping('203.0.113.123', 3, '00:00:00.200');

        $this->assertTrue($p->responde());
        $this->assertSame(3, $p->recibidos());
        $this->assertSame(0.356, $p->minMs());
        $this->assertSame(0.667, $p->avgMs());
        $this->assertSame(1.234, $p->maxMs());
        $this->assertSame(0.0, $p->perdidaPct());
    }

    public function test_ping_sin_respuesta(): void
    {
        $p = $this->sesion->ping('203.0.113.124', 3, '00:00:00.200');

        $this->assertFalse($p->responde());
        $this->assertSame(0, $p->recibidos());
        $this->assertSame(100.0, $p->perdidaPct());
        $this->assertNull($p->avgMs());
    }

    public function test_ping_con_un_paquete_perdido(): void
    {
        $p = $this->sesion->ping('203.0.113.133', 3, '00:00:00.200');

        $this->assertTrue($p->responde());
        $this->assertSame(2, $p->recibidos());
        $this->assertSame(33.33, $p->perdidaPct());
        $this->assertSame(0.3, $p->avgMs());
    }

    public function test_ping_trae_el_detalle_de_cada_paquete_tambien_los_perdidos(): void
    {
        $this->assertSame([
            ['seq' => 0, 'ms' => 0.356, 'estado' => 'ok'],
            ['seq' => 1, 'ms' => 0.412, 'estado' => 'ok'],
            ['seq' => 2, 'ms' => 1.234, 'estado' => 'ok'],
        ], $this->sesion->ping('203.0.113.123', 3, '00:00:00.200')->detalle);

        $this->assertSame(
            array_fill(0, 3, 'timeout'),
            array_column($this->sesion->ping('203.0.113.124', 3, '00:00:00.200')->detalle, 'estado')
        );
        $this->assertSame([null, null, null], array_column($this->sesion->ping('203.0.113.124', 3, '00:00:00.200')->detalle, 'ms'));
    }

    public function test_tasas_en_vivo_de_las_vlan_pedidas_como_winbox(): void
    {
        $t = $this->sesion->tasasEnVivo([100, 101, 103]);

        $this->assertSame([101, 100], array_column($t, 'vlan_id'));
        $porVlan = array_column($t, null, 'vlan_id');
        $this->assertSame(self::INTERFAZ_A, $porVlan[101]['nombre']);
        $this->assertSame('sfp-sfpplus1', $porVlan[101]['padre']);
        $this->assertSame(48213000, $porVlan[101]['rx_bps']);
        $this->assertSame(231004000, $porVlan[101]['tx_bps']);
        $this->assertSame(8100, $porVlan[100]['tx_pps']);
        $this->assertSame(1, $this->api->llamadas['/interface/monitor-traffic']);
    }

    public function test_error_del_router_en_el_ping_no_tumba_la_sesion(): void
    {
        $p = $this->sesion->ping('10.9.9.9', 3, '00:00:00.200');

        $this->assertNotNull($p->error);
        $this->assertFalse($p->responde());
        $this->assertSame('ROUTER DE PRUEBA', $this->sesion->identidad());
    }
}
