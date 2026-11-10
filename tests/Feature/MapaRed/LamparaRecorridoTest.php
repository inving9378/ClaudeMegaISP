<?php

namespace Tests\Feature\MapaRed;

use App\Modules\Addons\MapaRed\Services\RedGraphService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Item #1241 — lámpara: casos sintéticos del recorrido cliente→OLT (servicio de #1237). */
class LamparaRecorridoTest extends TestCase
{
    public function test_cliente_sin_conexion_no_tiene_puertos(): void
    {
        $this->assertSame([], app(RedGraphService::class)->puertosDeCliente(987654321));
    }

    public function test_puerto_de_cliente_sin_union_se_reporta_cortado(): void
    {
        $dev = DB::table('mapared_devices')->insertGetId(['name' => 'Cliente sintético', 'type' => 'client']);
        $port = DB::table('mapared_devices_ports')->insertGetId(['name' => '1', 'type' => 'in', 'device_id' => $dev, 'client_id' => 987654322]);

        $svc = app(RedGraphService::class);
        $this->assertSame([$port], $svc->puertosDeCliente(987654322));

        $r = $svc->trazarDiagramaDesdePuerto($port);
        $this->assertFalse($r['completa']);
        $this->assertStringContainsString('se corta en', $r['motivo_corte']);
        $this->assertSame([], $r['tramos']);
    }
}
