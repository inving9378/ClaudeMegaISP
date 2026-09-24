<?php

namespace App\Modules\Addons\VoIP\Services\Voz;

use App\Modules\Addons\Marketing\Services\Tts\OpenAiTtsDriver;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * MegaVoz Fase 6 — voz de "María". Envuelve el driver de TTS que YA EXISTE
 * (OpenAiTtsDriver, Marketing) en vez de duplicar la llamada HTTP — mismo
 * patrón que ya critica CobranzaTtsService por no reusarlo (ver bitácora
 * de Fase 7). La única pieza nueva aquí es la conversión a PCM crudo
 * 8kHz/mono/16-bit que AudioSocket necesita (OpenAI solo entrega MP3).
 */
class VoiceTtsService
{
    public function __construct(private OpenAiTtsDriver $driver = new OpenAiTtsDriver())
    {
    }

    /**
     * @return array{success: bool, pcm_path?: string, costo_usd?: float, error?: string}
     */
    public function sintetizar(string $texto, string $voz = 'nova'): array
    {
        $texto = trim($texto);
        if ($texto === '') {
            return ['success' => false, 'error' => 'Texto vacío'];
        }

        $dir = storage_path('app/megavoz-bot-audio');
        if (! is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }

        $slug   = 'tts_' . md5($texto . $voz) . '_' . uniqid();
        $mp3Path = $dir . '/' . $slug . '.mp3';
        $pcmPath = $dir . '/' . $slug . '.pcm';

        $resultado = $this->driver->synthesize($texto, $mp3Path, ['voice' => $voz]);
        if (! ($resultado['success'] ?? false)) {
            return ['success' => false, 'error' => $resultado['error'] ?? 'TTS falló'];
        }

        // MP3 → PCM crudo (sin cabecera) 8kHz/mono/16-bit signed LE — lo que
        // AudioSocket espera mandar directo como frames de audio. Mismo
        // ffmpeg que ya usa CobranzaTtsService::convertToWav(), aquí sin
        // envolver en WAV (-f s16le en vez de -f wav).
        // -af loudnorm: el MP3 que entrega OpenAI TTS viene con MUCHO
        // margen de headroom sin usar (medido en vivo 24-sep-2026: solo
        // ~27% del rango de 16-bit) — sonaba bajito Y "sucio" a la vez, las
        // dos quejas de David eran la MISMA causa: mu-law (el códec típico
        // de una llamada real) tiene más ruido de cuantización justo en
        // niveles bajos, así que "bajo volumen" se oye además "con estática".
        // loudnorm normaliza a -16 LUFS con techo de -1.5dB (nunca clipea)
        // — mismo archivo, mismo texto, solo más fuerte y limpio. Medido:
        // pico subió de 27% a ~84% del rango sin recortar.
        $proceso = new Process([
            'ffmpeg', '-y', '-i', $mp3Path,
            '-af', 'loudnorm=I=-16:TP=-1.5:LRA=11',
            '-ar', '8000', '-ac', '1', '-f', 's16le', '-acodec', 'pcm_s16le',
            $pcmPath,
        ]);
        $proceso->setTimeout(15);
        $proceso->run();

        @unlink($mp3Path);

        if (! $proceso->isSuccessful() || ! is_file($pcmPath)) {
            Log::warning('VoiceTtsService: ffmpeg no generó PCM — ' . $proceso->getErrorOutput());
            return ['success' => false, 'error' => 'No se pudo convertir el audio a PCM'];
        }

        return [
            'success'   => true,
            'pcm_path'  => $pcmPath,
            'costo_usd' => (float) ($resultado['cost_usd'] ?? 0),
        ];
    }

    /**
     * Variante CACHEADA — para texto FIJO que se repite entre llamadas (el
     * saludo inicial de María, único caso hoy). Encontrado en vivo
     * (2026-09-24): `AudioSocket()` en Asterisk trae su propio timeout de
     * inactividad de 2000ms (hard-coded en `app_audiosocket.c`, no
     * configurable) — si el primer audio que le mandamos al canal tarda
     * más que eso (llamada real HTTP a OpenAI + ffmpeg), Asterisk cuelga el
     * canal ANTES de que el saludo llegue a sonar (misma convención de "un
     * valor negativo de la app = colgar" del candado de pre-vuelo, pero
     * esta vez el daemon SÍ estaba disponible — el problema es la latencia,
     * no la disponibilidad). Con la respuesta cacheada en disco, el saludo
     * se manda casi instantáneo (solo leer el archivo), sin esperar a
     * OpenAI. Mismo patrón que `CobranzaTtsService::generateAudioCached()`
     * — cachea por hash del texto+voz, un archivo persistente por
     * combinación distinta, NUNCA se borra tras usarlo (a diferencia del
     * .pcm efímero de `sintetizar()`, que si se borra en cada turno).
     *
     * @return array{success: bool, pcm_path?: string, costo_usd?: float, error?: string, cacheado?: bool}
     */
    public function sintetizarCacheado(string $texto, string $voz = 'nova'): array
    {
        $texto = trim($texto);
        if ($texto === '') {
            return ['success' => false, 'error' => 'Texto vacío'];
        }

        $dir = storage_path('app/megavoz-bot-audio/cache');
        if (! is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }

        $slug    = md5($texto . '|' . $voz);
        $pcmPath = $dir . '/' . $slug . '.pcm';

        if (is_file($pcmPath) && filesize($pcmPath) > 0) {
            return ['success' => true, 'pcm_path' => $pcmPath, 'costo_usd' => 0.0, 'cacheado' => true];
        }

        $mp3Path = $dir . '/' . $slug . '.mp3';

        $resultado = $this->driver->synthesize($texto, $mp3Path, ['voice' => $voz]);
        if (! ($resultado['success'] ?? false)) {
            return ['success' => false, 'error' => $resultado['error'] ?? 'TTS falló'];
        }

        // -af loudnorm: el MP3 que entrega OpenAI TTS viene con MUCHO
        // margen de headroom sin usar (medido en vivo 24-sep-2026: solo
        // ~27% del rango de 16-bit) — sonaba bajito Y "sucio" a la vez, las
        // dos quejas de David eran la MISMA causa: mu-law (el códec típico
        // de una llamada real) tiene más ruido de cuantización justo en
        // niveles bajos, así que "bajo volumen" se oye además "con estática".
        // loudnorm normaliza a -16 LUFS con techo de -1.5dB (nunca clipea)
        // — mismo archivo, mismo texto, solo más fuerte y limpio. Medido:
        // pico subió de 27% a ~84% del rango sin recortar.
        $proceso = new Process([
            'ffmpeg', '-y', '-i', $mp3Path,
            '-af', 'loudnorm=I=-16:TP=-1.5:LRA=11',
            '-ar', '8000', '-ac', '1', '-f', 's16le', '-acodec', 'pcm_s16le',
            $pcmPath,
        ]);
        $proceso->setTimeout(15);
        $proceso->run();

        @unlink($mp3Path);

        if (! $proceso->isSuccessful() || ! is_file($pcmPath)) {
            Log::warning('VoiceTtsService: ffmpeg no generó PCM (cacheado) — ' . $proceso->getErrorOutput());
            return ['success' => false, 'error' => 'No se pudo convertir el audio a PCM'];
        }

        return [
            'success'   => true,
            'pcm_path'  => $pcmPath,
            'costo_usd' => (float) ($resultado['cost_usd'] ?? 0),
            'cacheado'  => false,
        ];
    }
}
