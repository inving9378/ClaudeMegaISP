<?php

namespace App\Modules\Addons\VoIP\Services;

use Symfony\Component\Process\Process;
use Throwable;

/**
 * Pasa una grabación de WAV a MP3 sin perderla en el camino (26-nov).
 *
 * Asterisk graba en WAV de 16 bits (~940 KB por minuto) aunque la línea solo trae audio
 * de teléfono (8 kHz). En MP3 de 32 kbps la misma llamada ocupa ~235 KB por minuto, un
 * 75% menos, y se oye igual. Se guarda a 16 kHz y no a 8: a 8 kHz el MP3 es la variante
 * «MPEG 2.5», que no todos los reproductores abren; a 16 kHz lo abre cualquiera
 * (navegador, iPhone, WhatsApp, Windows) y ocupa lo mismo, porque el tamaño lo pone el
 * bitrate.
 *
 * Orden, para que nunca quede una llamada sin audio:
 *   1. el MP3 se hace aparte (`.mp3.part`);
 *   2. se revisa que sea MP3 y que dure lo mismo que el WAV;
 *   3. se le ponen los permisos y la fecha del WAV (la purga de 90 días cuenta desde la
 *      llamada, no desde la conversión);
 *   4. se renombra a `.mp3` y se apunta la llamada en la base;
 *   5. solo entonces se quita el WAV.
 * Si algo falla antes del paso 5, el WAV se queda como estaba.
 */
class CompresorGrabaciones
{
    public const BITRATE    = '32k';
    public const FRECUENCIA = 16000;

    /** Diferencia de duración aceptada entre el WAV y el MP3 (el MP3 agrega unos ms de relleno). */
    private const TOLERANCIA_SEG = 1.0;

    public static function hayFfmpeg(): bool
    {
        foreach (['ffmpeg', 'ffprobe'] as $bin) {
            $p = new Process(['sh', '-c', 'command -v ' . $bin]);
            $p->run();
            if (! $p->isSuccessful()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  callable(string $wav, string $mp3): void  $apuntar  apunta la llamada al MP3 (nombres sin carpeta).
     *                                                            Si lanza, se borra el MP3 y el WAV queda intacto.
     * @return array{ok: bool, mp3: ?string, error: ?string, aviso: ?string, antes: int, despues: int}
     */
    public function comprimir(string $wav, callable $apuntar): array
    {
        $r = ['ok' => false, 'mp3' => null, 'error' => null, 'aviso' => null, 'antes' => 0, 'despues' => 0];

        if (! is_file($wav) || strtolower(substr($wav, -4)) !== '.wav') {
            return ['error' => 'no es un WAV que exista'] + $r;
        }

        $mp3   = substr($wav, 0, -4) . '.mp3';
        $tmp   = $mp3 . '.part';
        $antes = (int) filesize($wav);
        $mtime = (int) filemtime($wav);
        $modo  = fileperms($wav) & 0777;

        $original = $this->sondear($wav);
        if ($original === null) {
            return ['error' => 'ffprobe no pudo leer el WAV', 'antes' => $antes] + $r;
        }

        $cmd = array_merge($this->prioridadBaja(), [
            'ffmpeg', '-nostdin', '-hide_banner', '-loglevel', 'error', '-y',
            '-i', $wav, '-vn', '-ac', '1', '-ar', (string) self::FRECUENCIA,
            '-c:a', 'libmp3lame', '-b:a', self::BITRATE, '-f', 'mp3', $tmp,
        ]);
        $p = new Process($cmd);
        $p->setTimeout(600);

        try {
            $p->run();
        } catch (Throwable $e) {
            @unlink($tmp);
            return ['error' => 'ffmpeg: ' . $e->getMessage(), 'antes' => $antes] + $r;
        }
        if (! $p->isSuccessful() || ! is_file($tmp) || filesize($tmp) === 0) {
            @unlink($tmp);
            return ['error' => 'ffmpeg no pudo convertir: ' . trim($p->getErrorOutput()), 'antes' => $antes] + $r;
        }

        $nuevo = $this->sondear($tmp);
        if ($nuevo === null || $nuevo['codec'] !== 'mp3') {
            @unlink($tmp);
            return ['error' => 'el MP3 no se pudo leer', 'antes' => $antes] + $r;
        }
        if (abs($nuevo['duracion'] - $original['duracion']) > self::TOLERANCIA_SEG) {
            @unlink($tmp);
            return ['error' => sprintf('el MP3 dura %.1f s y el WAV %.1f s', $nuevo['duracion'], $original['duracion']), 'antes' => $antes] + $r;
        }

        @chmod($tmp, $modo);
        @touch($tmp, $mtime);
        if (! @rename($tmp, $mp3)) {
            @unlink($tmp);
            return ['error' => 'no se pudo dejar el MP3 en la carpeta', 'antes' => $antes] + $r;
        }

        try {
            $apuntar(basename($wav), basename($mp3));
        } catch (Throwable $e) {
            @unlink($mp3);
            return ['error' => 'no se pudo apuntar la llamada al MP3: ' . $e->getMessage(), 'antes' => $antes] + $r;
        }

        $despues = (int) filesize($mp3);
        $aviso   = @unlink($wav) ? null : 'la llamada ya usa el MP3, pero el WAV no se pudo quitar';

        return ['ok' => true, 'mp3' => $mp3, 'error' => null, 'aviso' => $aviso, 'antes' => $antes, 'despues' => $despues];
    }

    /** Borra los `.mp3.part` que dejó una conversión interrumpida. Son temporales, no grabaciones. */
    public function limpiarTemporales(string $dir, int $segundos = 3600): int
    {
        $n = 0;
        foreach (glob(rtrim($dir, '/') . '/*.mp3.part') ?: [] as $f) {
            if (@filemtime($f) < time() - $segundos && @unlink($f)) {
                $n++;
            }
        }

        return $n;
    }

    /** @return array{codec: string, duracion: float}|null */
    private function sondear(string $archivo): ?array
    {
        $p = new Process(['ffprobe', '-v', 'error', '-select_streams', 'a:0',
            '-show_entries', 'stream=codec_name:format=duration', '-of', 'json', $archivo]);
        $p->setTimeout(60);

        try {
            $p->run();
        } catch (Throwable) {
            return null;
        }
        if (! $p->isSuccessful()) {
            return null;
        }

        $j        = json_decode($p->getOutput(), true);
        $codec    = $j['streams'][0]['codec_name'] ?? null;
        $duracion = $j['format']['duration'] ?? null;

        return ($codec && is_numeric($duracion)) ? ['codec' => (string) $codec, 'duracion' => (float) $duracion] : null;
    }

    /** nice/ionice si están: la conversión no le quita CPU ni disco a las llamadas. */
    private function prioridadBaja(): array
    {
        $pre = [];
        if (is_executable('/usr/bin/nice')) {
            $pre = ['nice', '-n', '19'];
        }
        if (is_executable('/usr/bin/ionice')) {
            $pre = array_merge($pre, ['ionice', '-c3']);
        }

        return $pre;
    }
}
