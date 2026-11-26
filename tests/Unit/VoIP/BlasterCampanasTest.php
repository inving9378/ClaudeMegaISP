<?php

namespace Tests\Unit\VoIP;

use App\Modules\Addons\CobranzaBlaster\Console\AmiEventListenerCommand as Listener;
use App\Modules\Addons\CobranzaBlaster\Jobs\BlastCampanaJob;
use App\Modules\Addons\CobranzaBlaster\Jobs\ProcessCallResultJob as Resultado;
use App\Modules\Addons\CobranzaBlaster\Models\CobranzaLlamada;
use App\Modules\Addons\CobranzaBlaster\Services\AmiConnectionService;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Campañas/Blaster (25-nov): lo que falló en la prueba de punta a punta en test.
 * Sin BD ni Asterisk; la lectura del AMI usa un par de sockets locales.
 */
class BlasterCampanasTest extends TestCase
{
    // ── Teléfonos de la ficha del cliente ──────────────────────────────────────

    /** @dataProvider telefonos */
    public function test_el_numero_que_se_marca_son_10_digitos(?string $ficha, ?string $esperado): void
    {
        $this->assertSame($esperado, CobranzaLlamada::normalizarTelefono($ficha));
    }

    public static function telefonos(): array
    {
        return [
            'limpio'                          => ['5500000001', '5500000001'],
            'con espacios'                    => ['55 0000 0001', '5500000001'],
            'con guiones'                     => ['55-0000-0001', '5500000001'],
            'con +52'                         => ['+52 55 0000 0001', '5500000001'],
            'con +52 1'                       => ['+521 55 0000 0001', '5500000001'],
            'con 044'                         => ['044 55 0000 0001', '5500000001'],
            'dos números separados por espacios' => ['5500000001      5500000002', '5500000001'],
            'dos números con un espacio'      => ['5500000001 5500000002', '5500000001'],
            'dos con diagonal'                => ['55 0000 0001 / 55 0000 0002', '5500000001'],
            'dos con «y»'                     => ['5500000001 y 5500000002', '5500000001'],
            'uno incompleto y uno bueno'      => ['550000000 5500000002', '5500000002'],
            'dobles espacios dentro'          => ['55  0000  0001', '5500000001'],
            'mezcla de espacios dentro'       => ['55 0000  0001', '5500000001'],
            'con paréntesis y guion'          => ['(55) 0000-0001', '5500000001'],
            'con extensión'                   => ['33 0000 0001 ext 12', '3300000001'],
            'con extensión y paréntesis'      => ['(55) 0000-0001 ext. 12', '5500000001'],
            'incompleto'                      => ['550000000', null],
            'un dígito de más'                => ['55000000011', null],
            'vacío'                           => ['', null],
            'null'                            => [null, null],
            'sin dígitos'                     => ['sin teléfono', null],
        ];
    }

    // ── Eventos de Asterisk → evento del job ───────────────────────────────────

    /** @dataProvider eventos */
    public function test_cada_evento_de_asterisk_dice_lo_que_paso(array $evento, ?string $esperado): void
    {
        $this->assertSame($esperado, Listener::mapearEvento($evento));
    }

    public static function eventos(): array
    {
        $or = fn (string $r, int $reason) => ['Event' => 'OriginateResponse', 'Response' => $r, 'Reason' => (string) $reason];

        return [
            'contestó'                    => [['Event' => 'Newstate', 'ChannelStateDesc' => 'Up'], 'ANSWER'],
            'timbrando no cuenta'         => [['Event' => 'Newstate', 'ChannelStateDesc' => 'Ringing'], null],
            'originate bien = contestó'   => [$or('Success', 4), 'ANSWER'],
            'ocupado'                     => [$or('Failure', 5), 'BUSY'],
            'timbró y nadie contestó'     => [$or('Failure', 3), 'NOANSWER'],
            'colgaron antes de contestar' => [$or('Failure', 1), 'NOANSWER'],
            'no se pudo crear el canal'   => [$or('Failure', 0), 'FAILED'],
            'congestión'                  => [$or('Failure', 8), 'FAILED'],
            // La causa del Hangup ya no decide el estado (se pisaba con OriginateResponse).
            'colgó, causa ocupado'        => [['Event' => 'Hangup', 'Cause' => '17'], 'HANGUP'],
            'colgó, causa desconocida'    => [['Event' => 'Hangup', 'Cause' => '0'], 'HANGUP'],
            'marcó 1'                     => [['Event' => 'DTMFEnd', 'Digit' => '1'], 'DTMF'],
            'marcó otro'                  => [['Event' => 'DTMFEnd', 'Digit' => '5'], null],
            'otro evento'                 => [['Event' => 'VarSet'], null],
        ];
    }

    // ── Estado de la llamada ───────────────────────────────────────────────────

    public function test_contestada_gana_y_nada_la_vuelve_fallida(): void
    {
        $this->assertSame(['estado' => 'contestada', 'reintentar' => false], Resultado::siguienteEstado('ANSWER', 'marcando', 1, 3));
        $this->assertNull(Resultado::siguienteEstado('ANSWER', 'contestada', 1, 3));
        foreach (['FAILED', 'NOANSWER', 'BUSY', 'HANGUP'] as $e) {
            $this->assertNull(Resultado::siguienteEstado($e, 'contestada', 3, 3), $e);
        }
    }

    public function test_sin_contestar_ocupado_o_falla_se_reintentan_mientras_queden_intentos(): void
    {
        foreach (['NOANSWER', 'BUSY', 'FAILED'] as $e) {
            $this->assertSame(['estado' => 'pendiente', 'reintentar' => true], Resultado::siguienteEstado($e, 'marcando', 1, 3), $e);
        }
        $this->assertSame(['estado' => 'no_contesto', 'reintentar' => false], Resultado::siguienteEstado('NOANSWER', 'marcando', 3, 3));
        $this->assertSame(['estado' => 'ocupado', 'reintentar' => false], Resultado::siguienteEstado('BUSY', 'marcando', 3, 3));
        $this->assertSame(['estado' => 'fallida', 'reintentar' => false], Resultado::siguienteEstado('FAILED', 'marcando', 3, 3));
    }

    public function test_un_evento_repetido_o_tardio_no_reabre_la_llamada(): void
    {
        $this->assertNull(Resultado::siguienteEstado('NOANSWER', 'pendiente', 1, 3));
        $this->assertNull(Resultado::siguienteEstado('BUSY', 'ocupado', 3, 3));
        $this->assertNull(Resultado::siguienteEstado('FAILED', 'pagada', 1, 3));
        $this->assertNull(Resultado::siguienteEstado('HANGUP', 'marcando', 1, 3));
        $this->assertNull(Resultado::siguienteEstado('DTMF', 'contestada', 1, 3));
    }

    public function test_una_llamada_que_no_se_contesta_ya_no_suspende_al_cliente(): void
    {
        $codigo = file_get_contents((new \ReflectionClass(Resultado::class))->getFileName());
        $this->assertStringNotContainsString('SuspendService', $codigo);
        $this->assertStringNotContainsString('suspendServiceByClient', $codigo);
        $this->assertSame(0, (new \ReflectionMethod(Resultado::class, 'handle'))->getNumberOfParameters());
    }

    public function test_una_llamada_perdida_se_resuelve_con_lo_que_dejo_el_cdr(): void
    {
        $this->assertSame('ANSWER', BlastCampanaJob::eventoSegunCdr(['ANSWERED']));
        $this->assertSame('ANSWER', BlastCampanaJob::eventoSegunCdr(['NO ANSWER', 'ANSWERED']));
        $this->assertSame('BUSY', BlastCampanaJob::eventoSegunCdr(['BUSY']));
        $this->assertSame('FAILED', BlastCampanaJob::eventoSegunCdr(['FAILED']));
        $this->assertSame('FAILED', BlastCampanaJob::eventoSegunCdr(['CONGESTION']));
        $this->assertSame('NOANSWER', BlastCampanaJob::eventoSegunCdr(['NO ANSWER']));
        $this->assertSame('NOANSWER', BlastCampanaJob::eventoSegunCdr([]));   // sin CDR
    }

    public function test_un_solo_pase_del_blaster_a_la_vez(): void
    {
        $mw = (new BlastCampanaJob(1))->middleware();
        $this->assertCount(1, $mw);
        $this->assertInstanceOf(\Illuminate\Queue\Middleware\WithoutOverlapping::class, $mw[0]);
        // La misma llave para cualquier campaña: la troncal es una sola.
        $this->assertSame($mw[0]->key, (new BlastCampanaJob(2))->middleware()[0]->key);
    }

    // ── Fechas de la campaña ───────────────────────────────────────────────────

    public function test_la_campana_solo_marca_entre_sus_fechas(): void
    {
        $hoy = Carbon::parse('2026-11-25 10:00:00');
        $f   = fn (?string $d) => $d ? Carbon::parse($d) : null;

        $this->assertSame('si', BlastCampanaJob::enFechas(null, null, $hoy));
        $this->assertSame('si', BlastCampanaJob::enFechas($f('2026-11-25'), $f('2026-11-25'), $hoy));
        $this->assertSame('no_empieza', BlastCampanaJob::enFechas($f('2026-11-26'), null, $hoy));
        $this->assertSame('termino', BlastCampanaJob::enFechas(null, $f('2026-11-24'), $hoy));
        $this->assertSame('si', BlastCampanaJob::enFechas(null, $f('2026-11-25'), Carbon::parse('2026-11-25 23:59:00')));
    }

    // ── Respuestas del AMI ─────────────────────────────────────────────────────

    /** @return array{0: AmiConnectionService, 1: resource} servicio con un socket local y el otro extremo («Asterisk») */
    private function amiConSocket(): array
    {
        [$nuestro, $asterisk] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        stream_set_timeout($nuestro, 1);
        $ami = (new \ReflectionClass(AmiConnectionService::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(AmiConnectionService::class, 'socket'))->setValue($ami, $nuestro);

        return [$ami, $asterisk];
    }

    public function test_cada_accion_lee_su_respuesta_y_no_los_eventos_que_llegan_antes(): void
    {
        [$ami, $asterisk] = $this->amiConSocket();
        // Lo que pasó en test: tras el login llega FullyBooted y, con eventos, Newchannel;
        // antes el Originate leía eso como su respuesta y se daba por fallido.
        fwrite($asterisk, "Event: FullyBooted\r\nPrivilege: system,all\r\n\r\n"
            . "Event: Newchannel\r\nUniqueid: cob-6-1\r\n\r\n"
            . "Response: Success\r\nActionID: blaster-6-1\r\nMessage: Originate successfully queued\r\n\r\n"
            . "Response: Success\r\nActionID: blaster-7-1\r\nMessage: Originate successfully queued\r\n\r\n");

        $r = $ami->sendRaw("Action: Originate\r\nChannel: PJSIP/troncal/5500000007\r\nActionID: blaster-7-1\r\n\r\n");

        $this->assertStringContainsString('ActionID: blaster-7-1', $r);
        $this->assertStringContainsString('Response: Success', $r);
    }

    public function test_una_respuesta_de_error_tambien_se_reconoce(): void
    {
        [$ami, $asterisk] = $this->amiConSocket();
        fwrite($asterisk, "Event: Newchannel\r\nUniqueid: cob-8-1\r\n\r\n"
            . "Response: Error\r\nActionID: blaster-8-1\r\nMessage: Originate failed\r\n\r\n");

        $r = $ami->sendRaw("Action: Originate\r\nActionID: blaster-8-1\r\n\r\n");

        $this->assertStringContainsString('Response: Error', $r);
    }

    public function test_sin_respuesta_regresa_vacio_en_vez_de_un_evento(): void
    {
        [$ami, $asterisk] = $this->amiConSocket();
        fwrite($asterisk, "Event: Newchannel\r\nUniqueid: cob-9-1\r\n\r\n");

        $this->assertSame('', $ami->sendRaw("Action: Originate\r\nActionID: blaster-9-1\r\n\r\n"));
    }
}
