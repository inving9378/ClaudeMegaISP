<?php

namespace Tests\Unit\VoIP;

use App\Modules\Addons\VoIP\Services\HistorialLlamadasService as H;
use PHPUnit\Framework\TestCase;

/**
 * normalizar() junta las filas CDR de un linkedid en una llamada. Es pura (sin BD):
 * estas filas imitan lo que Asterisk escribe en voip_llamadas.
 */
class HistorialLlamadasNormalizarTest extends TestCase
{
    private function fila(array $c): array
    {
        return $c + [
            'id' => 1, 'uniqueid' => '100.1', 'linkedid' => '100.1', 'src' => null, 'dst' => 's',
            'dcontext' => 'grupo-1', 'channel' => null, 'dstchannel' => null, 'lastapp' => 'Queue',
            'disposition' => 'NO ANSWER', 'duration' => 0, 'billsec' => 0, 'grabacion' => null,
            'start' => '2026-11-10 10:00:00', 'answer' => null, 'end' => '2026-11-10 10:00:00',
        ];
    }

    public function test_entrante_contestada_por_un_agente_de_la_cola(): void
    {
        $l = H::normalizar([
            $this->fila(['src' => '+525512345678', 'channel' => 'PJSIP/trunk_2-0000001a', 'dstchannel' => 'PJSIP/1002-0000001b',
                'disposition' => 'NO ANSWER', 'duration' => 20, 'billsec' => 0, 'grabacion' => '100.1.wav',
                'start' => '2026-11-10 10:00:00', 'end' => '2026-11-10 10:00:20']),
            $this->fila(['src' => '+525512345678', 'channel' => 'PJSIP/trunk_2-0000001a', 'dstchannel' => 'PJSIP/1001-0000001c',
                'disposition' => 'ANSWERED', 'duration' => 140, 'billsec' => 120,
                'start' => '2026-11-10 10:00:05', 'end' => '2026-11-10 10:02:25']),
        ]);

        $this->assertSame('entrante', $l['direccion']);
        $this->assertSame('contestada', $l['estado']);
        $this->assertSame('1001', $l['extension']);
        $this->assertSame('5512345678', $l['numero']);
        $this->assertSame(120, $l['segundos']);
        $this->assertSame(25, $l['espera']);           // 5 s en la cola + 20 s timbrando
        $this->assertSame('100.1', $l['grabacion']);
        $this->assertSame([], $l['timbro_a']);          // la contestaron: no es «sin contestar» de nadie
        $this->assertSame('2026-11-10 10:02:25', $l['fin']);
    }

    public function test_entrante_que_atendio_el_asistente(): void
    {
        $l = H::normalizar([
            $this->fila(['src' => '5568175643', 'channel' => 'PJSIP/trunk_2-00000075', 'lastapp' => 'AudioSocket',
                'disposition' => 'ANSWERED', 'duration' => 108, 'billsec' => 108, 'grabacion' => 'x.wav']),
        ]);

        $this->assertSame('entrante', $l['direccion']);
        $this->assertSame('asistente', $l['estado']);
        $this->assertNull($l['extension']);
        $this->assertSame(108, $l['segundos']);
    }

    public function test_entrante_que_nadie_contesto_registra_a_quien_le_timbro(): void
    {
        $l = H::normalizar([
            $this->fila(['src' => '5500000001', 'channel' => 'Local/s@grupo-1-00000007;2', 'dstchannel' => 'PJSIP/1003-00000002',
                'disposition' => 'NO ANSWER', 'start' => '2026-11-10 10:00:00', 'end' => '2026-11-10 10:00:22']),
            $this->fila(['src' => '5500000001', 'channel' => 'Local/s@grupo-1-00000007;2', 'dstchannel' => 'PJSIP/web1004-00000003',
                'disposition' => 'NO ANSWER', 'start' => '2026-11-10 10:00:00', 'end' => '2026-11-10 10:00:22']),
        ]);

        $this->assertSame('entrante', $l['direccion']);
        $this->assertSame('no_atendida', $l['estado']);
        $this->assertSame(['1003', 'web1004'], $l['timbro_a']);
        $this->assertSame(22, $l['espera']);
    }

    public function test_interna_contestada(): void
    {
        $l = H::normalizar([
            $this->fila(['src' => '1003', 'dst' => '1001', 'dcontext' => 'from-internal', 'lastapp' => 'Dial',
                'channel' => 'PJSIP/web1003-0000006b', 'dstchannel' => 'PJSIP/1001-0000006c',
                'disposition' => 'ANSWERED', 'duration' => 92, 'billsec' => 86]),
        ]);

        $this->assertSame('interna', $l['direccion']);
        $this->assertSame('contestada', $l['estado']);
        $this->assertSame('web1003', $l['origen']);
        $this->assertSame('1001', $l['numero']);
        $this->assertSame('1001', $l['extension']);
        $this->assertSame(86, $l['segundos']);
    }

    public function test_interna_fallida_queda_sin_respuesta_y_sin_trabajador(): void
    {
        $l = H::normalizar([
            $this->fila(['src' => '1001', 'dst' => '2005', 'dcontext' => 'from-internal', 'lastapp' => 'Dial',
                'channel' => 'PJSIP/1001-00000073', 'disposition' => 'FAILED']),
        ]);

        $this->assertSame('interna', $l['direccion']);
        $this->assertSame('sin_respuesta', $l['estado']);
        $this->assertNull($l['extension']);
    }

    public function test_saliente_por_troncal(): void
    {
        $l = H::normalizar([
            $this->fila(['src' => '1001', 'dst' => '5598765432', 'dcontext' => 'from-internal', 'lastapp' => 'Dial',
                'channel' => 'PJSIP/1001-00000080', 'dstchannel' => 'PJSIP/trunk_2-00000081',
                'disposition' => 'ANSWERED', 'duration' => 65, 'billsec' => 60]),
        ]);

        $this->assertSame('saliente', $l['direccion']);
        $this->assertSame('contestada', $l['estado']);
        $this->assertSame('1001', $l['extension']);
        $this->assertSame('5598765432', $l['numero']);
        $this->assertSame(60, $l['segundos']);
    }

    public function test_endpoint_y_numero(): void
    {
        $this->assertSame('1001', H::endpointDe('PJSIP/1001-0000001a'));
        $this->assertSame('web1003', H::endpointDe('PJSIP/web1003-0000006b'));
        $this->assertSame('trunk_2', H::endpointDe('PJSIP/trunk_2-00000075'));
        $this->assertNull(H::endpointDe('Local/s@grupo-1-00000007;2'));
        $this->assertNull(H::endpointDe(null));

        $this->assertSame('5568175643', H::numeroExterno('+525568175643'));
        $this->assertSame('5568175643', H::numeroExterno('5215568175643'));
        $this->assertSame('5568175643', H::numeroExterno('"Juan" <5568175643>'));
        $this->assertSame('14155550100', H::numeroExterno('+14155550100'));
    }

    public function test_el_numero_sale_de_la_fila_de_la_cola_aunque_el_otro_lado_cambie_de_callerid(): void
    {
        // Lado de un puente (Local;1) cuyo CallerID Asterisk cambió al del agente.
        $l = H::normalizar([
            $this->fila(['src' => '1990', 'dcontext' => 'inbound-trunk-2', 'channel' => 'Local/s@inbound-trunk-2-00000005;1',
                'lastapp' => 'Wait', 'disposition' => 'ANSWERED', 'duration' => 25, 'billsec' => 25]),
            $this->fila(['src' => '5568175643', 'channel' => 'Local/s@inbound-trunk-2-00000005;2', 'dstchannel' => 'PJSIP/1001-0000000c',
                'disposition' => 'NO ANSWER', 'duration' => 2, 'billsec' => 2]),
            $this->fila(['src' => '5568175643', 'channel' => 'Local/s@inbound-trunk-2-00000005;2', 'dstchannel' => 'PJSIP/1990-0000000d',
                'disposition' => 'ANSWERED', 'duration' => 22, 'billsec' => 22]),
        ]);

        $this->assertSame('5568175643', $l['numero']);
        $this->assertSame('1990', $l['extension']);
        $this->assertSame('contestada', $l['estado']);
    }

    public function test_cada_ticket_cuenta_para_una_sola_llamada(): void
    {
        $llamadas = [
            ['id' => 'b', 'fin' => '2026-11-10 15:02:46'],
            ['id' => 'a', 'fin' => '2026-11-10 15:01:58'],
        ];
        // Un ticket cubre a las dos por tiempo, pero solo cuenta para la primera que terminó.
        $this->assertSame(['a'], H::emparejarTickets($llamadas, ['2026-11-10 15:03:07']));
        // Dos tickets: uno para cada llamada.
        $this->assertSame(['a', 'b'], H::emparejarTickets($llamadas, ['2026-11-10 15:03:07', '2026-11-10 15:05:00']));
        // Fuera de la ventana: ninguno.
        $this->assertSame([], H::emparejarTickets($llamadas, ['2026-11-10 15:30:00']));
    }

    public function test_ticket_cuenta_solo_dentro_de_los_15_minutos_despues_de_colgar(): void
    {
        $llamada = [['id' => 'x', 'fin' => '2026-11-10 10:02:25']];
        $this->assertSame(['x'], H::emparejarTickets($llamada, ['2026-11-10 10:05:00']));
        $this->assertSame(['x'], H::emparejarTickets($llamada, ['2026-11-10 10:17:25']));   // justo al límite
        $this->assertSame([], H::emparejarTickets($llamada, ['2026-11-10 10:17:26']));
        $this->assertSame([], H::emparejarTickets($llamada, ['2026-11-10 10:01:00']));      // antes de colgar
        $this->assertSame([], H::emparejarTickets($llamada, []));
    }
}
