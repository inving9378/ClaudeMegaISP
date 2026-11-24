<?php

namespace Tests\Unit\VoIP;

use App\Modules\Addons\CobranzaBlaster\Controllers\DashboardController as D;
use App\Modules\Addons\VoIP\Services\HistorialLlamadasService as H;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

/** Llamadas de Campañas/Blaster en el historial y los cálculos del Dashboard del módulo. Sin BD. */
class HistorialCampanasTest extends TestCase
{
    private function fila(array $c): array
    {
        return $c + [
            'id' => 1, 'uniqueid' => '100.1', 'linkedid' => '100.1', 'src' => null, 'dst' => 's',
            'dcontext' => 'from-internal', 'channel' => null, 'dstchannel' => null, 'lastapp' => 'Dial',
            'disposition' => 'NO ANSWER', 'duration' => 0, 'billsec' => 0, 'grabacion' => null,
            'start' => '2026-11-24 10:00:00', 'answer' => null, 'end' => '2026-11-24 10:00:00',
        ];
    }

    public function test_la_que_origina_una_campana_es_de_campana_y_se_puede_escuchar(): void
    {
        $l = H::normalizar([
            $this->fila(['uniqueid' => 'cob-57-1795000000', 'linkedid' => 'cob-57-1795000000', 'src' => '5500000001',
                'dcontext' => 'cobranza-blaster', 'channel' => 'PJSIP/servnet-00000010', 'lastapp' => 'Background',
                'disposition' => 'ANSWERED', 'duration' => 31, 'billsec' => 28, 'grabacion' => 'cob-57-1795000000.wav']),
        ], 'servnet', 'cobranza-blaster');

        $this->assertSame('campana', $l['direccion']);
        $this->assertSame('contestada', $l['estado']);
        $this->assertSame(28, $l['segundos']);
        $this->assertSame(57, $l['campana_llamada_id']);
        $this->assertSame('cob-57-1795000000', $l['grabacion']);
        $this->assertNull($l['numero'], 'el número sale de cobranza_llamadas, no del CDR');
    }

    public function test_campana_que_nadie_contesto(): void
    {
        $l = H::normalizar([
            $this->fila(['uniqueid' => 'cob-58-1795000100', 'linkedid' => 'cob-58-1795000100', 'dcontext' => 'cobranza-blaster',
                'channel' => 'PJSIP/servnet-00000011', 'lastapp' => 'AppDial2', 'disposition' => 'NO ANSWER']),
        ], 'servnet', 'cobranza-blaster');

        $this->assertSame('campana', $l['direccion']);
        $this->assertSame('sin_respuesta', $l['estado']);
        $this->assertNull($l['grabacion']);
    }

    public function test_lo_que_entra_por_la_troncal_de_campanas_es_entrante(): void
    {
        $l = H::normalizar([
            $this->fila(['src' => '5534000000', 'dcontext' => 'from-servnet', 'channel' => 'PJSIP/servnet-00000012',
                'dstchannel' => 'PJSIP/2007-00000013', 'disposition' => 'ANSWERED', 'duration' => 40, 'billsec' => 35,
                'grabacion' => '100.1.wav']),
        ], 'servnet', 'cobranza-blaster');

        $this->assertSame('entrante', $l['direccion']);
        $this->assertSame('contestada', $l['estado']);
        $this->assertSame('2007', $l['extension']);
        $this->assertSame('100.1', $l['grabacion']);
    }

    public function test_sin_la_troncal_de_campanas_sigue_como_antes(): void
    {
        $l = H::normalizar([
            $this->fila(['src' => '1001', 'dst' => '1002', 'channel' => 'PJSIP/1001-00000001', 'dstchannel' => 'PJSIP/1002-00000002',
                'disposition' => 'ANSWERED', 'billsec' => 10]),
        ]);

        $this->assertSame('interna', $l['direccion']);
        $this->assertNull($l['campana_llamada_id']);
        $this->assertNull(H::llamadaDeCampana('1795000000.12'));
        $this->assertSame(9, H::llamadaDeCampana('cob-9-1795000000'));
    }

    private function llamada(array $c): array
    {
        return $c + ['inicio' => '2026-11-24 10:00:00', 'direccion' => 'entrante', 'estado' => 'contestada', 'segundos' => 0,
            'grabacion' => null, 'numero' => null, 'origen' => null, 'cliente' => null, 'trabajador' => null, 'campana' => null];
    }

    public function test_resumen_y_por_dia_del_dashboard(): void
    {
        $llamadas = collect([
            $this->llamada(['estado' => 'contestada', 'segundos' => 60, 'grabacion' => '1.1']),
            $this->llamada(['estado' => 'asistente', 'segundos' => 20]),
            $this->llamada(['estado' => 'no_atendida', 'inicio' => '2026-11-23 09:00:00']),
            $this->llamada(['direccion' => 'saliente', 'estado' => 'sin_respuesta']),
        ]);

        $r = D::resumen($llamadas);
        $this->assertSame(4, $r['total']);
        $this->assertSame(2, $r['contestadas']);
        $this->assertSame(1, $r['perdidas'], 'entrante que nadie atendió');
        $this->assertSame(1, $r['sin_respuesta'], 'saliente que no contestaron');
        $this->assertSame(50, $r['contestadas_pct']);
        $this->assertSame(80, $r['segundos_total']);
        $this->assertSame(40, $r['duracion_prom']);
        $this->assertSame(1, $r['con_grabacion']);
        $this->assertSame(1, $r['sin_grabar'], 'el asistente contestó y no hay grabación');

        $dias = D::porDia($llamadas, Carbon::parse('2026-11-22'), Carbon::parse('2026-11-24')->endOfDay());
        $this->assertSame(['2026-11-22', '2026-11-23', '2026-11-24'], array_column($dias, 'fecha'));
        $this->assertSame([0, 0, 2], array_column($dias, 'contestadas'));
        $this->assertSame([0, 1, 0], array_column($dias, 'perdidas'));
        $this->assertSame([0, 0, 1], array_column($dias, 'sin_respuesta'));

        $this->assertNull(D::resumen(collect())['contestadas_pct']);
    }

    public function test_busqueda_por_numero_cliente_trabajador_o_campana(): void
    {
        $llamadas = collect([
            $this->llamada(['numero' => '5512345678', 'cliente' => 'Ana Ruiz']),
            $this->llamada(['direccion' => 'campana', 'numero' => '5587654321', 'campana' => 'Cobranza noviembre']),
            $this->llamada(['direccion' => 'interna', 'origen' => '1003', 'numero' => '2007', 'trabajador' => 'Luis Gómez (2007)']),
        ]);

        $this->assertCount(1, D::buscar($llamadas, '55 1234'));
        $this->assertCount(1, D::buscar($llamadas, 'ruiz'));
        $this->assertCount(1, D::buscar($llamadas, 'NOVIEMBRE'));
        $this->assertCount(1, D::buscar($llamadas, 'luis'));
        $this->assertCount(1, D::buscar($llamadas, '1003'));
        $this->assertCount(3, D::buscar($llamadas, '  '));
    }
}
