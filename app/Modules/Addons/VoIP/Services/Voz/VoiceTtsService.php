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
        $proceso = new Process([
            'ffmpeg', '-y', '-i', $mp3Path,
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
}
