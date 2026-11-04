<?php

namespace Tests\Unit\Metricas;

use App\Services\Metricas\LecturaPing;
use App\Services\Metricas\ResumenPing;
use PHPUnit\Framework\TestCase;

/** Item #988: lectura de los tiempos de RouterOS y estado del cliente por sus IPs. */
class PingTest extends TestCase
{
    /** @dataProvider tiempos */
    public function test_tiempos_de_routeros_a_milisegundos(?string $tiempo, ?float $ms): void
    {
        $this->assertSame($ms, LecturaPing::tiempoEnMs($tiempo));
    }

    public static function tiempos(): array
    {
        return [
            'v7 microsegundos'    => ['356us', 0.356],
            'v7 ms y us'          => ['1ms234us', 1.234],
            'solo ms'             => ['12ms', 12.0],
            'v6 menor a 1 ms'     => ['<1ms', 0.5],
            'segundos'            => ['1s5ms', 1005.0],
            'vacío'               => ['', null],
            'sin dato'            => [null, null],
            'basura'              => ['n/a', null],
        ];
    }

    private function ping(array $rtts, int $enviados = 3): LecturaPing
    {
        return new LecturaPing($enviados, $rtts);
    }

    public function test_en_linea_si_responden_todas(): void
    {
        $r = ResumenPing::de([$this->ping([0.3, 0.4, 0.35]), $this->ping([0.2, 0.2, 0.2])]);

        $this->assertSame('en_linea', $r['estado_ping']);
        $this->assertSame(2, $r['ping_ips']);
        $this->assertSame(2, $r['ping_responden']);
        $this->assertSame(0.275, $r['lat_avg_ms']);
        $this->assertSame(0.4, $r['lat_max_ms']);
        $this->assertSame(0.0, $r['loss_pct']);
    }

    public function test_degradado_si_responde_solo_una(): void
    {
        $r = ResumenPing::de([$this->ping([0.3, 0.3, 0.3]), $this->ping([])]);

        $this->assertSame('degradado', $r['estado_ping']);
        $this->assertSame(1, $r['ping_responden']);
        $this->assertSame(0.3, $r['lat_avg_ms']);
        $this->assertSame(50.0, $r['loss_pct']);
    }

    public function test_sin_respuesta_si_ninguna(): void
    {
        $r = ResumenPing::de([$this->ping([]), $this->ping([])]);

        $this->assertSame('sin_respuesta', $r['estado_ping']);
        $this->assertSame(0, $r['ping_responden']);
        $this->assertNull($r['lat_avg_ms']);
        $this->assertSame(100.0, $r['loss_pct']);
    }

    public function test_ip_no_medida_o_con_error_no_cuenta(): void
    {
        $r = ResumenPing::de([$this->ping([0.3, 0.3, 0.3]), null, LecturaPing::conError(3, 'no route')]);

        $this->assertSame('en_linea', $r['estado_ping']);
        $this->assertSame(1, $r['ping_ips']);
    }

    public function test_sin_nada_medido_no_hay_estado(): void
    {
        $r = ResumenPing::de([null]);

        $this->assertNull($r['estado_ping']);
        $this->assertNull($r['ping_ips']);
    }
}
