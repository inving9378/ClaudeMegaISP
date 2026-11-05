<?php

namespace Tests\Unit\Metricas;

use App\Services\Metricas\HistoricoMinutos;
use PHPUnit\Framework\TestCase;

/**
 * Item #988 Fase 4: indicadores, eventos y series del histórico de un dedicado, sin base de datos.
 */
class HistoricoMinutosTest extends TestCase
{
    private int $t0;

    protected function setUp(): void
    {
        $this->t0 = strtotime('2026-10-30 10:00:00');
    }

    public function test_la_disponibilidad_no_cuenta_los_huecos_del_colector(): void
    {
        $i = $this->historico(10, $this->caidaYHueco());

        $ind = $i->indicadores();
        $this->assertSame(8, $ind['minutos_medidos']);
        $this->assertSame(2, $ind['minutos_sin_respuesta']);
        $this->assertSame(2, $ind['minutos_sin_datos']);
        $this->assertSame(75.0, $ind['disponibilidad_pct']);
        $this->assertSame(100.0, $ind['perdida_max_pct']);
    }

    public function test_eventos_del_periodo_el_mas_reciente_primero(): void
    {
        $eventos = $this->historico(10, $this->caidaYHueco())->eventos();

        $this->assertSame([HistoricoMinutos::SIN_DATOS, HistoricoMinutos::SIN_RESPUESTA], array_column($eventos, 'tipo'));
        $this->assertSame($this->t0 + 480, $eventos[0]['inicio']);
        $this->assertSame(2, $eventos[0]['minutos']);
        $this->assertSame('No hubo lectura del colector', $eventos[0]['detalle']);
        $this->assertSame($this->t0 + 360, $eventos[1]['inicio']);
        $this->assertSame($this->t0 + 480, $eventos[1]['fin']);
        $this->assertSame('Ningún equipo respondió · interfaz VLAN caída', $eventos[1]['detalle']);
    }

    public function test_degradado_dice_que_equipo_responde_y_no_se_repite_como_perdida_alta(): void
    {
        $filas = $pings = [];
        for ($m = 0; $m < 6; $m++) {
            $filas[$this->t($m)] = $this->fila('degradado', loss: 50.0, responden: 1);
            $pings[$this->t($m)] = ['192.0.2.10' => $this->ping(3), '192.0.2.11' => $this->ping(0)];
        }

        $eventos = $this->historico(6, $filas, $pings)->eventos();

        $this->assertCount(1, $eventos);
        $this->assertSame(HistoricoMinutos::DEGRADADO, $eventos[0]['tipo']);
        $this->assertSame('Responde solo .10', $eventos[0]['detalle']);
    }

    public function test_perdida_alta_necesita_cinco_minutos_de_los_equipos_que_responden(): void
    {
        $filas = $pings = [];
        foreach ([0, 1, 2, 3, 4, 6, 7, 8, 9] as $m) {
            $filas[$this->t($m)] = $this->fila('en_linea', loss: 33.33);
            $pings[$this->t($m)] = ['192.0.2.10' => $this->ping(2), '192.0.2.11' => $this->ping(2)];
        }
        $filas[$this->t(5)] = $this->fila('en_linea');
        $pings[$this->t(5)] = ['192.0.2.10' => $this->ping(3), '192.0.2.11' => $this->ping(3)];

        $eventos = $this->historico(10, $filas, $pings)->eventos();

        $this->assertCount(1, $eventos, 'el tramo de 4 minutos no llega a «Pérdida alta»');
        $this->assertSame(HistoricoMinutos::PERDIDA_ALTA, $eventos[0]['tipo']);
        $this->assertSame($this->t0, $eventos[0]['inicio']);
        $this->assertSame(5, $eventos[0]['minutos']);
        $this->assertSame('Pérdida promedio 33 %', $eventos[0]['detalle']);
    }

    public function test_latencia_alta_es_relativa_al_promedio_reciente_y_mayor_a_2_ms(): void
    {
        $filas = [];
        for ($m = 0; $m < 8; $m++) {
            $filas[$this->t($m)] = $this->fila('en_linea', lat: match (true) {
                $m < 3  => 4.5,
                $m < 5  => 0.3,
                default => 1.8,
            });
        }

        $conBase03 = $this->historico(8, $filas, [], [$this->t0 => 0.3])->eventos();
        $this->assertCount(1, $conBase03);
        $this->assertSame(HistoricoMinutos::LATENCIA_ALTA, $conBase03[0]['tipo']);
        $this->assertSame(3, $conBase03[0]['minutos']);
        $this->assertSame('Latencia promedio 4.5 ms', $conBase03[0]['detalle']);

        // 1.8 ms es más de 10 veces 0.1 ms, pero no pasa de 2 ms: no es «Latencia alta».
        $conBase01 = $this->historico(8, $filas, [], [$this->t0 => 0.1])->eventos();
        $this->assertSame([3], array_column($conBase01, 'minutos'));
    }

    public function test_las_series_cortan_en_los_huecos_y_conservan_el_maximo(): void
    {
        $filas = [];
        foreach ([10, 20, 30, 40, 500] as $m => $mbps) {
            $filas[$this->t($m)] = $this->fila('en_linea', bajada: $mbps * 1_000_000);
        }

        $s = (new HistoricoMinutos($this->t0, $this->t(15), $filas))
            ->series($this->t0, $this->t0 + 3600, 300, ['192.0.2.10'], $this->t(15));

        $this->assertCount(3, $s['bajada'], 'las cubetas del futuro no se incluyen');
        $this->assertSame([($this->t0 + 150) * 1000, 120_000_000.0, 500_000_000.0], $s['bajada'][0]);
        $this->assertSame([null, null], array_slice($s['bajada'][1], 1), 'un hueco es corte de línea, no cero');
        $this->assertSame([null, null], array_slice($s['latencia']['192.0.2.10'][0], 1));
    }

    public function test_el_total_de_trafico_usa_el_tiempo_real_entre_lecturas(): void
    {
        $filas = [
            $this->t(0) => $this->fila('en_linea', bajada: 8_000_000),
            $this->t(1) => $this->fila(null, estado: 'router_sin_conexion', bajada: null),
            $this->t(2) => $this->fila('en_linea', bajada: 8_000_000),
        ];

        $ind = $this->historico(3, $filas)->indicadores();

        // 8 Mbps × 60 s + 8 Mbps × 120 s (la lectura del minuto 2 promedia desde el minuto 0).
        $this->assertSame(180_000_000, $ind['total_bajada_bytes']);
        $this->assertSame(8_000_000, $ind['pico_bajada_bps']);
        $this->assertSame(1, $ind['minutos_sin_datos']);
    }

    public function test_los_minutos_degradados_se_informan_sin_bajar_la_disponibilidad(): void
    {
        $filas = $pings = [];
        for ($m = 0; $m < 6; $m++) {
            $filas[$this->t($m)] = $this->fila('en_linea', loss: 33.33);
            $pings[$this->t($m)] = ['192.0.2.10' => $this->ping(2), '192.0.2.11' => $this->ping(2)];
        }
        foreach ([6, 7] as $m) {
            $filas[$this->t($m)] = $this->fila('degradado', loss: 50.0, responden: 1);
            $pings[$this->t($m)] = ['192.0.2.10' => $this->ping(3), '192.0.2.11' => $this->ping(0)];
        }

        $ind = $this->historico(8, $filas, $pings)->indicadores();

        $this->assertSame(8, $ind['minutos_degradado']);
        $this->assertSame(100.0, $ind['disponibilidad_pct'], 'solo «sin respuesta» baja la disponibilidad');
    }

    public function test_el_tiempo_en_pausa_no_es_caida_ni_hueco_y_no_cuenta_en_la_disponibilidad(): void
    {
        // Minutos 0-3 en línea, 4-5 sin respuesta, 6-9 sin lectura pero en pausa.
        $filas = [];
        foreach ([0, 1, 2, 3] as $m) {
            $filas[$this->t($m)] = $this->fila('en_linea');
        }
        foreach ([4, 5] as $m) {
            $filas[$this->t($m)] = $this->fila('sin_respuesta', lat: null, loss: 100.0, responden: 0);
        }
        $pausas = [['desde' => $this->t(6), 'hasta' => null, 'motivo' => 'cliente bloqueado']];

        $calculo = new HistoricoMinutos($this->t0, $this->t(10), $filas, [], [], [], $pausas);
        $ind = $calculo->indicadores();
        $eventos = $calculo->eventos();

        $this->assertSame(6, $ind['minutos_medidos']);
        $this->assertSame(4, $ind['minutos_pausado']);
        $this->assertSame(0, $ind['minutos_sin_datos'], 'la pausa no es un hueco del colector');
        $this->assertEqualsWithDelta(66.67, $ind['disponibilidad_pct'], 0.01, 'la pausa no baja la disponibilidad');
        $this->assertSame([HistoricoMinutos::PAUSADO, HistoricoMinutos::SIN_RESPUESTA], array_column($eventos, 'tipo'));
        $this->assertSame('Monitoreo pausado: cliente bloqueado', $eventos[0]['detalle']);
        $this->assertSame(4, $eventos[0]['minutos']);
        $this->assertCount(1, HistoricoMinutos::bandas($eventos)[HistoricoMinutos::PAUSADO]);
    }

    public function test_bandas_de_sin_respuesta_y_sin_datos_en_milisegundos(): void
    {
        $b = HistoricoMinutos::bandas($this->historico(10, $this->caidaYHueco())->eventos());

        $this->assertSame([[($this->t0 + 360) * 1000, ($this->t0 + 480) * 1000]], $b[HistoricoMinutos::SIN_RESPUESTA]);
        $this->assertSame([[($this->t0 + 480) * 1000, ($this->t0 + 600) * 1000]], $b[HistoricoMinutos::SIN_DATOS]);
    }

    /** Minutos 0-5 en línea, 6-7 sin respuesta con la interfaz caída, 8-9 sin lectura. */
    private function caidaYHueco(): array
    {
        $filas = [];
        for ($m = 0; $m < 6; $m++) {
            $filas[$this->t($m)] = $this->fila('en_linea');
        }
        foreach ([6, 7] as $m) {
            $filas[$this->t($m)] = $this->fila('sin_respuesta', estado: 'interfaz_caida', lat: null, loss: 100.0, responden: 0, bajada: 0);
        }

        return $filas;
    }

    private function historico(int $minutos, array $filas, array $pings = [], array $base = []): HistoricoMinutos
    {
        return new HistoricoMinutos($this->t0, $this->t($minutos), $filas, $pings, $base);
    }

    private function t(int $minuto): int
    {
        return $this->t0 + 60 * $minuto;
    }

    private function fila(?string $estadoPing, string $estado = 'ok', ?float $lat = 0.3, float $loss = 0.0, int $responden = 2, ?int $bajada = 1_000_000): array
    {
        return [
            'estado' => $estado, 'estado_ping' => $estadoPing, 'lat_avg_ms' => $estadoPing === null ? null : $lat,
            'lat_max_ms' => $estadoPing === null || $lat === null ? null : $lat + 0.1, 'loss_pct' => $estadoPing === null ? null : $loss,
            'bajada_bps' => $bajada, 'subida_bps' => $bajada === null ? null : intdiv($bajada, 10),
            'ping_ips' => $estadoPing === null ? null : 2, 'ping_responden' => $estadoPing === null ? null : $responden,
        ];
    }

    private function ping(int $recibidos, int $enviados = 3, float $lat = 0.3): array
    {
        return ['enviados' => $enviados, 'recibidos' => $recibidos, 'lat_avg_ms' => $recibidos > 0 ? $lat : null, 'lat_max_ms' => $recibidos > 0 ? $lat + 0.1 : null];
    }
}
