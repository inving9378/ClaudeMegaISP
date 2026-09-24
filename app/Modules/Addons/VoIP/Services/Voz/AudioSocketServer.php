<?php

namespace App\Modules\Addons\VoIP\Services\Voz;

/**
 * MegaVoz Fase 6 — protocolo AudioSocket de Asterisk sobre UNA conexión TCP ya
 * aceptada. No existía ningún código de AudioSocket en el repo — esto es la
 * implementación del protocolo en sí (documentado por Asterisk, no una
 * librería): cada mensaje es 1 byte de tipo + 2 bytes de longitud
 * (big-endian) + el payload.
 *
 *   0x00 hangup  (payload vacío — Asterisk avisa que colgaron)
 *   0x01 uuid    (16 bytes — el primer mensaje que manda Asterisk)
 *   0x10 audio   (PCM crudo, 16-bit signed LE, 8kHz, mono — típico 320
 *                 bytes = 20ms por frame)
 *   0xff error
 *
 * Full-duplex: se puede leer y escribir en cualquier momento sobre el mismo
 * socket — no hay "turnos" a nivel protocolo, los turnos de conversación los
 * gobierna quien use esta clase (el daemon).
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

    /** @var resource */
    private $conn;

    private bool $colgado = false;

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
     * Acumula audio hasta detectar que el que llama terminó de hablar (VAD
     * simple por energía RMS) o hasta $maxSegundos. Devuelve la ruta a un
     * WAV temporal listo para VoiceSttService, o null si colgaron/no hubo
     * nada que transcribir.
     */
    public function escucharTurno(int $silencioMsCorte = 900, int $maxSegundos = 15): ?string
    {
        $pcm = '';
        $huboVoz = false;
        $framesSilencioSeguidos = 0;
        $framesSilencioParaCortar = (int) ($silencioMsCorte / self::MS_POR_FRAME);
        $framesMax = (int) (($maxSegundos * 1000) / self::MS_POR_FRAME);
        $frames = 0;

        // Umbral de energía: RMS bajo (~200 sobre 16-bit) ya se considera
        // silencio — conservador a propósito, mejor perder un poco de
        // sensibilidad que cortar a media palabra por ruido de línea.
        $umbralRms = 250.0;

        while ($frames < $framesMax) {
            $frame = $this->leerFrame();
            if (! $frame) {
                // Socket cerrado o timeout de lectura.
                break;
            }
            if ($frame['tipo'] === self::TIPO_HANGUP) {
                $this->colgado = true;
                break;
            }
            if ($frame['tipo'] !== self::TIPO_AUDIO) {
                continue;
            }

            $pcm .= $frame['payload'];
            $frames++;

            $rms = $this->rms($frame['payload']);
            if ($rms >= $umbralRms) {
                $huboVoz = true;
                $framesSilencioSeguidos = 0;
            } elseif ($huboVoz) {
                $framesSilencioSeguidos++;
                if ($framesSilencioSeguidos >= $framesSilencioParaCortar) {
                    break;
                }
            }
        }

        if (! $huboVoz || $pcm === '') {
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

        $offset = 0;
        $total  = strlen($pcm);
        while ($offset < $total && ! $this->colgado) {
            $chunk = substr($pcm, $offset, self::BYTES_POR_FRAME);
            // Último frame más corto que 320 bytes: se rellena con silencio
            // (ceros) — Asterisk espera el tamaño de frame exacto.
            if (strlen($chunk) < self::BYTES_POR_FRAME) {
                $chunk = str_pad($chunk, self::BYTES_POR_FRAME, "\x00");
            }
            if (! $this->escribirFrame(self::TIPO_AUDIO, $chunk)) {
                $this->colgado = true;
                break;
            }
            $offset += self::BYTES_POR_FRAME;
            // Ritmo real (20ms/frame) — mandar todo de golpe desborda el
            // jitter buffer del lado de Asterisk y suena entrecortado.
            usleep(self::MS_POR_FRAME * 1000);
        }
    }

    public function colgado(): bool
    {
        return $this->colgado;
    }

    public function cerrar(): void
    {
        if (is_resource($this->conn)) {
            @fclose($this->conn);
        }
    }

    // ── Protocolo, bajo nivel ───────────────────────────────────────────────

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
        $buffer = '';
        while (strlen($buffer) < $bytes) {
            $trozo = fread($this->conn, $bytes - strlen($buffer));
            if ($trozo === false || $trozo === '') {
                $meta = stream_get_meta_data($this->conn);
                if (feof($this->conn) || ($meta['timed_out'] ?? false)) {
                    return null;
                }
                continue;
            }
            $buffer .= $trozo;
        }
        return $buffer;
    }

    private function escribirFrame(int $tipo, string $payload): bool
    {
        $cabecera = chr($tipo) . pack('n', strlen($payload));
        $escrito = @fwrite($this->conn, $cabecera . $payload);
        return $escrito !== false;
    }

    private function rms(string $pcm16leBytes): float
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
