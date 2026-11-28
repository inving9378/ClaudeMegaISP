<?php

namespace Tests\Unit\VoIP;

use App\Modules\Addons\VoIP\Services\Voz\AudioSocketServer;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\TestCase;

/**
 * Protocolo AudioSocket con un par de sockets de verdad (un extremo hace de Asterisk). 27-nov:
 * lo que llega mientras la asistente habla se descarta, la frase de espera suena en otro
 * proceso sin romper los frames, y colgar se nota también mientras habla.
 */
class AudioSocketAsistenteTest extends TestCase
{
    private string $tmp;

    /** @var resource */
    private $asterisk;

    private AudioSocketServer $socket;

    protected function setUp(): void
    {
        if (! function_exists('pcntl_fork') || ! function_exists('posix_kill')) {
            $this->markTestSkipped('Requiere pcntl y posix.');
        }
        $this->tmp = sys_get_temp_dir() . '/audiosocket-' . uniqid();
        mkdir($this->tmp . '/storage/app', 0777, true);
        new Application($this->tmp);

        [$servidor, $this->asterisk] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        stream_set_timeout($servidor, 5);
        stream_set_timeout($this->asterisk, 5);
        $this->socket = new AudioSocketServer($servidor);
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
        exec('rm -rf ' . escapeshellarg($this->tmp));
    }

    private function frame(int $tipo, string $payload = ''): string
    {
        return chr($tipo) . pack('n', strlen($payload)) . $payload;
    }

    /** 20 ms de un tono con energía de voz (RMS ~ 3000). */
    private function voz(): string
    {
        $pcm = '';
        for ($i = 0; $i < 160; $i++) {
            $pcm .= pack('v', (int) (4000 * sin($i / 3)) & 0xFFFF);
        }
        return $this->frame(0x10, $pcm);
    }

    private function silencio(): string
    {
        return $this->frame(0x10, str_repeat("\x00", 320));
    }

    /** Asterisk manda $datos dentro de $ms, ya con el turno escuchándose (otro proceso). */
    private function mandarDespues(string $datos, int $ms = 150): void
    {
        $pid = pcntl_fork();
        if ($pid === 0) {
            usleep($ms * 1000);
            fwrite($this->asterisk, $datos);
            posix_kill(posix_getpid(), SIGKILL);
        }
    }

    /** @return array<int, array{tipo: int, len: int}> */
    private function leerFramesDelServidor(): array
    {
        stream_set_blocking($this->asterisk, false);
        $datos = '';
        while (($t = fread($this->asterisk, 65536)) !== false && $t !== '') {
            $datos .= $t;
        }
        stream_set_blocking($this->asterisk, true);

        $frames = [];
        for ($p = 0; $p + 3 <= strlen($datos);) {
            $len = unpack('n', substr($datos, $p + 1, 2))[1];
            $this->assertLessThanOrEqual(strlen($datos), $p + 3 + $len, 'frame cortado a la mitad');
            $frames[] = ['tipo' => ord($datos[$p]), 'len' => $len];
            $p += 3 + $len;
        }
        return $frames;
    }

    public function test_lo_que_llego_mientras_hablaba_no_abre_el_turno(): void
    {
        // Eco/ruido acumulado mientras la asistente hablaba: 1 s de «voz».
        fwrite($this->asterisk, str_repeat($this->voz(), 50));
        $this->socket->drenar();

        // Más eco que llega justo antes de escuchar: también se descarta.
        fwrite($this->asterisk, str_repeat($this->voz(), 10));
        // Ahora sí habla el cliente: 0.5 s de voz y 1 s de silencio.
        $this->mandarDespues(str_repeat($this->voz(), 25) . str_repeat($this->silencio(), 50));
        $wav = $this->socket->escucharTurno(800, 10);

        $this->assertNotNull($wav);
        // Cabecera de 44 bytes + 25 frames de voz + los 40 de silencio que cierran el turno.
        $this->assertSame(44 + (25 + 40) * 320, filesize($wav));
        $this->assertFalse($this->socket->vozCorta());
    }

    public function test_un_frame_a_medias_queda_para_la_siguiente_lectura(): void
    {
        $voz = $this->voz();
        fwrite($this->asterisk, $voz . substr($voz, 0, 100));
        $this->socket->drenar();
        $this->mandarDespues(substr($voz, 100) . str_repeat($this->voz(), 20) . str_repeat($this->silencio(), 50));

        $wav = $this->socket->escucharTurno(800, 10);

        // El frame que quedó a medias se completa y cuenta (no se desfasa): 21 de voz + 40 de silencio.
        $this->assertNotNull($wav);
        $this->assertSame(44 + (21 + 40) * 320, filesize($wav));
    }

    public function test_un_dictado_largo_no_se_corta_por_la_espera_inicial(): void
    {
        // Espera de 2 s para empezar; tarda 1.5 s en hablar y luego dicta 3 s sin parar.
        $this->mandarDespues(str_repeat($this->silencio(), 75) . str_repeat($this->voz(), 150) . str_repeat($this->silencio(), 60));

        $wav = $this->socket->escucharTurno(1000, 2);

        $this->assertNotNull($wav);
        // 75 de silencio inicial + 150 de voz + 50 de silencio que cierran el turno.
        $this->assertSame(44 + (75 + 150 + 50) * 320, filesize($wav));
    }

    public function test_un_si_rapido_no_cuenta_como_silencio(): void
    {
        // 140 ms de voz (menos del mínimo para transcribir) y luego silencio.
        $this->mandarDespues(str_repeat($this->voz(), 7) . str_repeat($this->silencio(), 120));
        $this->assertNull($this->socket->escucharTurno(1000, 2));
        $this->assertTrue($this->socket->vozCorta());

        // Silencio de verdad.
        $this->mandarDespues(str_repeat($this->silencio(), 120));
        $this->assertNull($this->socket->escucharTurno(1000, 2));
        $this->assertFalse($this->socket->vozCorta());
    }

    public function test_un_si_de_200_ms_si_se_transcribe(): void
    {
        $this->mandarDespues(str_repeat($this->voz(), 11) . str_repeat($this->silencio(), 60));

        $this->assertNotNull($this->socket->escucharTurno(1000, 2));
    }

    public function test_colgar_entre_lo_acumulado_se_nota(): void
    {
        fwrite($this->asterisk, str_repeat($this->voz(), 5) . $this->frame(0x00));
        $this->socket->drenar();

        $this->assertTrue($this->socket->colgado());
        $this->assertNull($this->socket->escucharTurno(800, 10));
    }

    public function test_la_frase_de_espera_suena_en_otro_proceso_y_luego_silencio(): void
    {
        $pcm = $this->tmp . '/frase.pcm';
        file_put_contents($pcm, str_repeat("\x11\x22", 160 * 10)); // 10 frames

        $fondo = $this->socket->reproducirDeFondo($pcm);
        $this->assertNotNull($fondo);
        usleep(400_000); // mientras tanto, «se piensa la respuesta»
        $this->socket->detenerFondo($fondo);

        $frames = $this->leerFramesDelServidor();
        $this->assertFalse($this->socket->colgado());
        $this->assertGreaterThanOrEqual(10, count($frames), 'la frase completa');
        $this->assertGreaterThan(10, count($frames), 'y silencio después, para mantener viva la línea');
        $this->assertSame([0x10], array_values(array_unique(array_column($frames, 'tipo'))));
        $this->assertSame([320], array_values(array_unique(array_column($frames, 'len'))));
    }

    public function test_detener_deja_terminar_la_frase(): void
    {
        $pcm = $this->tmp . '/frase.pcm';
        file_put_contents($pcm, str_repeat("\x11\x22", 160 * 25)); // 0.5 s

        $fondo = $this->socket->reproducirDeFondo($pcm);
        $this->socket->detenerFondo($fondo); // de inmediato

        $this->assertGreaterThanOrEqual(25, count($this->leerFramesDelServidor()));
    }

    public function test_si_cuelgan_mientras_suena_el_fondo_se_nota(): void
    {
        $fondo = $this->socket->reproducirDeFondo(null);
        usleep(100_000);
        // shutdown y no fclose: el proceso de fondo heredó este extremo y lo mantendría abierto.
        stream_socket_shutdown($this->asterisk, STREAM_SHUT_RDWR);
        usleep(100_000);
        $this->socket->detenerFondo($fondo);

        $this->assertTrue($this->socket->colgado());
    }

    public function test_colgar_mientras_habla_corta_la_respuesta(): void
    {
        $pcm = $this->tmp . '/respuesta.pcm';
        file_put_contents($pcm, str_repeat("\x00\x01", 160 * 100)); // 2 s
        fwrite($this->asterisk, $this->frame(0x00));

        $t0 = microtime(true);
        $this->socket->enviarAudio($pcm);

        $this->assertTrue($this->socket->colgado());
        $this->assertLessThan(1.0, microtime(true) - $t0, 'no termina de decir los 2 s');
    }
}
