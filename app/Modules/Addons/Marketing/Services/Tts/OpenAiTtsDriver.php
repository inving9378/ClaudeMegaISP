<?php

namespace App\Modules\Addons\Marketing\Services\Tts;

use App\Modules\Addons\IA\Services\IA;
use App\Modules\Addons\IA\Services\IANoConfigurada;
use App\Modules\Addons\Marketing\Services\Video\FFmpegService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Texto a voz con la API de audio de OpenAI. Lo comparten los videos de
 * Marketing ("marketing.voz_videos") y el bot de voz ("voip.voz"): cada uno
 * con su propia asignación en Integraciones → Módulos IA. La llave se lee en
 * CADA síntesis (el bot de voz es un proceso que corre días).
 */
class OpenAiTtsDriver implements TtsDriverInterface
{
    public function __construct(int $companyId = 1, protected string $claveIa = 'marketing.voz_videos')
    {
    }

    public function synthesize(string $text, string $outputPath, array $options = []): array
    {
        try {
            $c = IA::credencialesPara($this->claveIa);
        } catch (IANoConfigurada $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        $model   = $options['model']  ?? $c['modelo'];
        $voice   = $options['voice']  ?? 'nova';
        $speed   = $options['speed']  ?? 1.0;
        $format  = 'mp3';

        try {
            $response = Http::withToken((string) $c['llave'])
                ->timeout(60)
                ->post($c['base'] . '/audio/speech', [
                    'model'            => $model,
                    'input'            => $text,
                    'voice'            => $voice,
                    'response_format'  => $format,
                    'speed'            => (float) $speed,
                ]);

            if ($response->status() !== 200) {
                $error = $response->json('error.message') ?? $response->body();
                Log::channel('marketing')->error("OpenAI TTS error {$response->status()}: {$error}");
                return ['success' => false, 'error' => "OpenAI TTS error {$response->status()}: {$error}"];
            }

            // Ensure output directory exists
            $dir = dirname($outputPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }

            file_put_contents($outputPath, $response->body());

            // Get duration via ffprobe
            $ffmpeg   = new FFmpegService();
            $info     = $ffmpeg->probe($outputPath);
            $duration = $info['duration'] ?? 0;

            // Cost: tts-1 = $0.015 / 1000 chars; tts-1-hd = $0.030 / 1000 chars
            $costPer1k = ($model === 'tts-1-hd') ? 0.030 : 0.015;
            $cost      = (mb_strlen($text) / 1000) * $costPer1k;
            IA::registrarUsoIntegracion($c['integracion'], $this->claveIa, round($cost, 6));

            return [
                'success'      => true,
                'duration_sec' => $duration,
                'cost_usd'     => round($cost, 6),
                'voice'        => $voice,
                'chars'        => mb_strlen($text),
            ];
        } catch (\Throwable $e) {
            Log::channel('marketing')->error('OpenAI TTS exception: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function listVoices(): array
    {
        return [
            ['id' => 'nova',    'name' => 'Nova',    'gender' => 'female',  'recommended_for' => 'es_MX'],
            ['id' => 'alloy',   'name' => 'Alloy',   'gender' => 'neutral'],
            ['id' => 'echo',    'name' => 'Echo',    'gender' => 'male'],
            ['id' => 'fable',   'name' => 'Fable',   'gender' => 'neutral'],
            ['id' => 'onyx',    'name' => 'Onyx',    'gender' => 'male'],
            ['id' => 'shimmer', 'name' => 'Shimmer', 'gender' => 'female'],
        ];
    }

    public function getDriverName(): string
    {
        return 'openai';
    }
}
