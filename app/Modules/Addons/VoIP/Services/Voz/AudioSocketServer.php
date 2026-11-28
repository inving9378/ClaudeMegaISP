<?php

namespace App\Modules\Addons\VoIP\Services\Voz;

/**
 * MegaVoz Fase 6 — protocolo AudioSocket de Asterisk sobre UNA conexión TCP ya aceptada.
 * Cada mensaje es 1 byte de tipo + 2 bytes de longitud (big-endian) + el payload.
 *
 *   0x00 hangup  (payload vacío — Asterisk avisa que colgaron)
 *   0x01 uuid    (16 bytes — el primer mensaje que manda Asterisk)
 *   0x10 audio   (PCM crudo, 16-bit signed LE, 8kHz, mono — 320 bytes = 20 ms por frame)
 *   0xff error
 *
 * Full-duplex: Asterisk manda el audio del que llama todo el tiempo, también mientras la
 * asistente habla. 27-nov:
 *  - Lo que llega mientras ella habla se descarta antes de escuchar el siguiente turno. Antes
 *    se acumulaba y abría el turno siguiente: el eco de su propia voz y el ruido de la línea
 *    se mandaban a transcribir (de ahí los «¡Muchas gracias!» que nadie dijo).
 *  - reproducirDeFondo(): la frase de espera suena MIENTRAS se transcribe, se piensa y se
 *    genera la respuesta (antes sonaba primero y todo lo demás esperaba a que terminara).
 *  - numeroQueLlama(): el plan de marcado mete el número del que llama en el UUID (AudioSocket
 *    no manda nada más), para que la asistente sepa si es un cliente.
 */
class AudioSocketServer
{
    private const TIPO_HANGUP = 0x00;
    private const TIPO_UUID   = 0x01;
    private const TIPO_AUDIO  = 0x10;
    private const TIPO_ERROR  = 0xff;

    // 8kHz, 16-bit (2 bytes), mono, 20ms por frame — el tamaño estándar que
    // Asterisk manda/espera por frame de audio.
    private const BYTES_POR_FRAME = 320;
    private const MS_POR_FRAME    = 20;

    /** Tercer grupo del UUID cuando el plan de marcado trae el número del que llama. */
    public const MARCA_NUMERO = 'ca11';

    /** Lo más que puede sonar el reproductor de fondo (red de seguridad si nadie lo detiene). */
    private const FONDO_MAX_SEGUNDOS = 60;

    /** Lo más que se escucha un turno ya empezado a hablar (un dictado largo, una dirección). */
    public const MAX_HABLA_SEGUNDOS = 30;

    /** @var resource */
    private $conn;

    private bool $colgado = false;

    /** El último turno tuvo voz, pero tan corta que no se mandó a transcribir (no era silencio). */
    private bool $vozCorta = false;

    /** Bytes ya leídos del socket que aún no forman un frame completo (o que no se han usado). */
    private string $entrada = '';

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    /** Primer mensaje de la conexión — el UUID que Asterisk asignó a la llamada. */
    public function leerUuid(): ?string
    {
        $frame = $this->leerFrame();
        if (! $frame || $frame['tipo'] !== self::TIPO_UUID) {
            return null;
        }
        // 16 bytes crudos → representación hexadecimal legible (para logs/call_id).
        return bin2hex($frame['payload']);
    }

    /**
     * El número del que llama, si el plan de marcado lo metió en el UUID:
     * xxxxxxxx-xxxx-ca11-NNNN-NNNNNNNNNNNN, con el número rellenado a la izquierda con «f».
     * null si el UUID no trae la marca (plan de marcado viejo) o si la llamada no traía número.
     */
    public static function numeroQueLlama(?string $uuidHex): ?string
    {
        $hex = strtolower(preg_replace('/[^0-9a-fA-F]/', '', (string) $uuidHex));
        if (strlen($hex) !== 32 || substr($hex, 12, 4) !== self::MARCA_NUMERO) {
            return null;
        }
        $numero = ltrim(substr($hex, 16), 'f');

        return ctype_digit($numero) ? $numero : null;
    }

    /**
     * Acumula audio hasta detectar que el que llama terminó de hablar (VAD simple por energía
     * RMS). $maxSegundos es cuánto se espera a que EMPIECE a hablar; ya hablando, se le escucha
     * hasta MAX_HABLA_SEGUNDOS (27-nov: antes un solo tope contaba también el silencio inicial y
     * un dictado largo se cortaba). Devuelve la ruta a un WAV temporal listo para
     * VoiceSttService, o null si colgaron o no hubo nada que transcribir (vozCorta() dice si
     * hubo voz demasiado corta, que no es lo mismo que silencio).
     */
    public function escucharTurno(int $silencioMsCorte = 1000, int $maxSegundos = 15): ?string
    {
        $this->vozCorta = false;

        // Lo que llegó mientras la asistente hablaba (su eco, ruido) no es el turno del cliente.
        $this->drenar();
        if ($this->colgado) {
            return null;
        }

        $pcm = '';
        $huboVoz = false;
        $framesSilencioSeguidos = 0;
        $framesSilencioParaCortar = (int) ($silencioMsCorte / self::MS_POR_FRAME);
        $framesEspera = (int) (($maxSegundos * 1000) / self::MS_POR_FRAME);
        $framesHablaMax = (int) ((self::MAX_HABLA_SEGUNDOS * 1000) / self::MS_POR_FRAME);
        $frames = 0;
        $framesDesdeVoz = 0;

        // Umbral de energía — SUBIDO de 250 a 500 (24-sep-2026, prueba real
        // contra el troncal de Irving): con 250, puro ruido de línea de una
        // llamada real ya disparaba "detecté voz" y Whisper alucinaba texto
        // ("¡SUSCRÍBETE!") con turnos hechos de ruido. 500 sigue muy por
        // debajo de la energía típica de una voz real hablando.
        $umbralRms = 500.0;

        // Además del umbral por energía: exigir un mínimo de tiempo sostenido con esa energía
        // antes de tratarlo como habla real — un blip de ruido de 20-40 ms no es una palabra.
        // 200 ms (27-nov, antes 300): un «sí» rápido no llegaba y contaba como silencio.
        $framesVozMinimos = (int) (200 / self::MS_POR_FRAME);
        $framesConVoz = 0;

        while ($huboVoz ? $framesDesdeVoz < $framesHablaMax : $frames < $framesEspera) {
            $frame = $this->leerFrame();
            if (! $frame) {
                // Socket cerrado o timeout de lectura.
                break;
            }
            if ($frame['tipo'] === self::TIPO_HANGUP || $frame['tipo'] === self::TIPO_ERROR) {
                $this->colgado = true;
                break;
            }
            if ($frame['tipo'] !== self::TIPO_AUDIO) {
                continue;
            }

            $pcm .= $frame['payload'];
            $frames++;
            if ($huboVoz) {
                $framesDesdeVoz++;
            }

            $rms = self::rms($frame['payload']);
            if ($rms >= $umbralRms) {
                $huboVoz = true;
                $framesConVoz++;
                $framesSilencioSeguidos = 0;
            } elseif ($huboVoz) {
                $framesSilencioSeguidos++;
                if ($framesSilencioSeguidos >= $framesSilencioParaCortar) {
                    break;
                }
            }
        }

        if (! $huboVoz || $pcm === '' || $framesConVoz < $framesVozMinimos) {
            $this->vozCorta = $huboVoz;
            return null;
        }

        return $this->escribirWav($pcm);
    }

    /** Manda audio PCM crudo (de VoiceTtsService) como frames, a ritmo real. */
    public function enviarAudio(string $pcmPath): void
    {
        $pcm = @file_get_contents($pcmPath);
        if ($pcm === false || $pcm === '') {
            return;
        }

        $inicio = microtime(true);
        $total  = strlen($pcm);
        for ($offset = 0, $n = 0; $offset < $total && ! $this->colgado; $offset += self::BYTES_POR_FRAME, $n++) {
            // Último frame más corto que 320 bytes: se rellena con silencio (ceros).
            $chunk = str_pad(substr($pcm, $offset, self::BYTES_POR_FRAME), self::BYTES_POR_FRAME, "\x00");
            if (! $this->escribirFrame(self::TIPO_AUDIO, $chunk)) {
                $this->colgado = true;
                break;
            }
            // Cada 200 ms se vacía lo que va llegando: si cuelgan mientras habla, se nota aquí
            // y no después de decir toda la respuesta; y el buffer del socket no se llena.
            if ($n % 10 === 9) {
                $this->drenar();
            }
            // Ritmo real contra el reloj (20 ms por frame, sin acumular retraso): mandar todo
            // de golpe desborda el jitter buffer de Asterisk y suena entrecortado.
            $espera = $inicio + ($n + 1) * self::MS_POR_FRAME / 1000 - microtime(true);
            if ($espera > 0) {
                usleep((int) ($espera * 1_000_000));
            }
        }
    }

    /**
     * Pone a sonar $pcmPath (y luego silencio) en un proceso aparte, para que la llamada no se
     * quede muda mientras se transcribe, se piensa y se genera la respuesta. Sin la frase,
     * solo silencio: igual mantiene vivo el canal (AudioSocket cuelga si pasan ~2 s sin audio
     * del servidor). Hay que llamar a detenerFondo() antes de volver a usar el socket.
     *
     * @return array{pid: int, canal: resource}|null null si no se pudo (entonces ya sonó aquí mismo)
     */
    public function reproducirDeFondo(?string $pcmPath): ?array
    {
        $pcm = $pcmPath ? (string) @file_get_contents($pcmPath) : '';

        $par = function_exists('pcntl_fork') && function_exists('posix_kill')
            ? @stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP)
            : false;

        // SIGTERM bloqueada hasta que el hijo ponga su manejador: si la respuesta queda lista
        // antes, el aviso espera en vez de matarlo a media frase.
        $mascara = [];
        if ($par) {
            pcntl_sigprocmask(SIG_BLOCK, [SIGTERM], $mascara);
        }
        $pid = $par ? pcntl_fork() : -1;

        if ($pid === -1) {
            if ($par) {
                pcntl_sigprocmask(SIG_SETMASK, $mascara);
                fclose($par[0]);
                fclose($par[1]);
            }
            $this->enviarPcm($pcm);
            return null;
        }

        if ($pid === 0) {
            fclose($par[0]);
            $parar = false;
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, function () use (&$parar) {
                $parar = true;
            });
            pcntl_sigprocmask(SIG_SETMASK, $mascara);

            $sigueVivo = $this->sonarHastaQueParen($pcm, $parar);
            @fwrite($par[1], $sigueVivo ? 'O' : 'H');
            @fclose($par[1]);
            // Sin destructores ni shutdown de Laravel: la conexión de BD y el socket son del padre.
            posix_kill(posix_getpid(), SIGKILL);
            exit(0);
        }

        pcntl_sigprocmask(SIG_SETMASK, $mascara);
        fclose($par[1]);

        return ['pid' => $pid, 'canal' => $par[0]];
    }

    /** Termina el reproductor de fondo: deja acabar la frase y espera a que suelte el socket. */
    public function detenerFondo(?array $fondo): void
    {
        if (! $fondo) {
            return;
        }
        @posix_kill($fondo['pid'], SIGTERM);
        pcntl_waitpid($fondo['pid'], $estado);
        $resultado = @fread($fondo['canal'], 1);
        @fclose($fondo['canal']);
        if ($resultado !== 'O') {
            $this->colgado = true;
        }
    }

    public function colgado(): bool
    {
        return $this->colgado;
    }

    public function vozCorta(): bool
    {
        return $this->vozCorta;
    }

    public function cerrar(): void
    {
        if (is_resource($this->conn)) {
            @fclose($this->conn);
        }
    }

    /**
     * Lee sin esperar todo lo que ya llegó y descarta los frames completos (audio viejo).
     * Si entre ellos viene el aviso de colgado, lo marca. Un frame a medias se queda en el
     * buffer para la siguiente lectura.
     */
    public function drenar(): void
    {
        if ($this->colgado || ! is_resource($this->conn)) {
            return;
        }

        stream_set_blocking($this->conn, false);
        try {
            while (($trozo = fread($this->conn, 65536)) !== false && $trozo !== '') {
                $this->entrada .= $trozo;
            }
            if (feof($this->conn)) {
                $this->colgado = true;
            }
        } finally {
            stream_set_blocking($this->conn, true);
        }

        $pos = 0;
        $largo = strlen($this->entrada);
        while ($largo - $pos >= 3) {
            $tipo = ord($this->entrada[$pos]);
            $longitud = unpack('n', substr($this->entrada, $pos + 1, 2))[1];
            if ($largo - $pos < 3 + $longitud) {
                break;
            }
            $pos += 3 + $longitud;
            if ($tipo === self::TIPO_HANGUP || $tipo === self::TIPO_ERROR) {
                $this->colgado = true;
            }
        }
        $this->entrada = (string) substr($this->entrada, $pos);
    }

    // ── Protocolo, bajo nivel ───────────────────────────────────────────────

    /** Lo que hace el proceso de fondo: la frase, luego silencio hasta que lo detengan. */
    private function sonarHastaQueParen(string $pcm, bool &$parar): bool
    {
        $silencio = str_repeat("\x00", self::BYTES_POR_FRAME);
        $padre = posix_getppid();
        $inicio = microtime(true);
        $total = strlen($pcm);

        for ($n = 0; ; $n++) {
            $offset = $n * self::BYTES_POR_FRAME;
            if ($offset < $total) {
                $chunk = str_pad(substr($pcm, $offset, self::BYTES_POR_FRAME), self::BYTES_POR_FRAME, "\x00");
            } elseif ($parar || posix_getppid() !== $padre || microtime(true) - $inicio > self::FONDO_MAX_SEGUNDOS) {
                return true;
            } else {
                $chunk = $silencio;
            }

            if (! $this->escribirFrame(self::TIPO_AUDIO, $chunk)) {
                return false;
            }

            $espera = $inicio + ($n + 1) * self::MS_POR_FRAME / 1000 - microtime(true);
            if ($espera > 0) {
                usleep((int) ($espera * 1_000_000));
            }
        }
    }

    /** Igual que enviarAudio() pero con el PCM ya en memoria (respaldo sin fork). */
    private function enviarPcm(string $pcm): void
    {
        if ($pcm === '') {
            return;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'megavoz');
        file_put_contents($tmp, $pcm);
        $this->enviarAudio($tmp);
        @unlink($tmp);
    }

    private function leerFrame(): ?array
    {
        $cabecera = $this->leerExacto(3);
        if ($cabecera === null) {
            return null;
        }
        $tipo = ord($cabecera[0]);
        $longitud = unpack('n', substr($cabecera, 1, 2))[1];

        $payload = $longitud > 0 ? $this->leerExacto($longitud) : '';
        if ($payload === null) {
            return null;
        }

        return ['tipo' => $tipo, 'payload' => $payload];
    }

    private function leerExacto(int $bytes): ?string
    {
        while (strlen($this->entrada) < $bytes) {
            $trozo = fread($this->conn, max(8192, $bytes - strlen($this->entrada)));
            if ($trozo === false || $trozo === '') {
                $meta = stream_get_meta_data($this->conn);
                if (feof($this->conn) || ($meta['timed_out'] ?? false)) {
                    return null;
                }
                continue;
            }
            $this->entrada .= $trozo;
        }

        $salida = substr($this->entrada, 0, $bytes);
        $this->entrada = (string) substr($this->entrada, $bytes);

        return $salida;
    }

    private function escribirFrame(int $tipo, string $payload): bool
    {
        $cabecera = chr($tipo) . pack('n', strlen($payload));
        $escrito = @fwrite($this->conn, $cabecera . $payload);
        return $escrito !== false && $escrito > 0;
    }

    public static function rms(string $pcm16leBytes): float
    {
        $muestras = unpack('v*', $pcm16leBytes);
        if (! $muestras) {
            return 0.0;
        }
        $suma = 0.0;
        $n = count($muestras);
        foreach ($muestras as $u) {
            // unpack('v') da unsigned 16-bit — convertir a signed.
            $s = $u >= 32768 ? $u - 65536 : $u;
            $suma += $s * $s;
        }
        return $n > 0 ? sqrt($suma / $n) : 0.0;
    }

    private function escribirWav(string $pcm): string
    {
        $dir = storage_path('app/megavoz-bot-audio');
        if (! is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }
        $path = $dir . '/turno_' . uniqid() . '.wav';

        $sampleRate = 8000;
        $bitsPerSample = 16;
        $channels = 1;
        $byteRate = $sampleRate * $channels * $bitsPerSample / 8;
        $blockAlign = $channels * $bitsPerSample / 8;
        $dataSize = strlen($pcm);

        $header = 'RIFF' . pack('V', 36 + $dataSize) . 'WAVE'
            . 'fmt ' . pack('V', 16) . pack('v', 1) . pack('v', $channels)
            . pack('V', $sampleRate) . pack('V', $byteRate) . pack('v', $blockAlign) . pack('v', $bitsPerSample)
            . 'data' . pack('V', $dataSize);

        file_put_contents($path, $header . $pcm);

        return $path;
    }
}
