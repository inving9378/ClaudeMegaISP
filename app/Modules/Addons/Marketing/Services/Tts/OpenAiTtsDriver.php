<?php

namespace App\Modules\Addons\Marketing\Services\Tts;

use App\Models\Marketing\Setting;
use App\Modules\Addons\Marketing\Services\Video\FFmpegService;
use App\Services\Core\UsesApiIntegration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAiTtsDriver implements TtsDriverInterface
{
    use UsesApiIntegration;
    protected string $apiKey;
    protected string $endpoint = 'https://api.openai.com/v1/audio/speech';

    public function __construct(int $companyId = 1)
    {
        $this->apiKey = $this->resolveApiKey('openai', 'OPENAI_API_KEY', 'openai_api_key', $companyId) ?? '';
    }

    public function synthesize(string $text, string $outputPath, array $options = []): array
    {
        if (empty($this->apiKey)) {
            return ['success' => false, 'error' => 'OpenAI API key not configured (setting: openai_api_key)'];
        }

        $model   = $options['model']  ?? 'tts-1';
        $voice   = $options['voice']  ?? 'nova';
        $speed   = $options['speed']  ?? 1.0;
        $format  = 'mp3';

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(60)
                ->post($this->endpoint, [
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
