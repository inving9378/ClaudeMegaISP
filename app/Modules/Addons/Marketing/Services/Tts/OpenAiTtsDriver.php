<?php

namespace App\Modules\Addons\Marketing\Services\Tts;

use App\Modules\Addons\IA\Services\IA;
use App\Modules\Addons\IA\Services\IANoConfigurada;
use App\Modules\Addons\Marketing\Services\Video\FFmpegService;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
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
        return $this->synthesizeMany([[$text, $outputPath]], $options)[0];
    }

    /**
     * Varios textos a la vez (peticiones en paralelo, 27-nov): el bot de voz parte su respuesta
     * en oraciones y así espera lo que tarda la más larga, no la suma de todas. Mismo resultado
     * por elemento que synthesize().
     *
     * @param array<int, array{0: string, 1: string}> $items [texto, ruta del mp3]
     * @return array<int, array{success: bool, error?: string, duration_sec?: float, cost_usd?: float, voice?: string, chars?: int}>
     */
    public function synthesizeMany(array $items, array $options = []): array
    {
        $items = array_values($items);

        try {
            $c = IA::credencialesPara($this->claveIa);
        } catch (IANoConfigurada $e) {
            return array_fill(0, count($items), ['success' => false, 'error' => $e->getMessage()]);
        }

        $model = $options['model'] ?? $c['modelo'];
        $voice = $options['voice'] ?? 'nova';
        $timeout = (int) ($options['timeout'] ?? 60);

        $peticion = fn ($http, string $text) => $http->withToken((string) $c['llave'])
            ->timeout($timeout)
            ->post($c['base'] . '/audio/speech', $this->cuerpo($text, $model, $voice, $options));

        try {
            $respuestas = count($items) === 1
                ? [$peticion(Http::getFacadeRoot(), $items[0][0])]
                : Http::pool(fn (Pool $pool) => array_map(fn ($item) => $peticion($pool, $item[0]), $items));
        } catch (\Throwable $e) {
            Log::channel('marketing')->error('OpenAI TTS exception: ' . $e->getMessage());
            return array_fill(0, count($items), ['success' => false, 'error' => $e->getMessage()]);
        }

        $resultados = [];
        foreach ($items as $i => [$text, $outputPath]) {
            $resultados[$i] = $this->guardar($respuestas[$i] ?? null, $text, $outputPath, $model, $voice, $options, $c['integracion']);
        }

        return $resultados;
    }

    private function cuerpo(string $text, string $model, string $voice, array $options): array
    {
        $cuerpo = [
            'model'            => $model,
            'input'            => $text,
            'voice'            => $voice,
            'response_format'  => 'mp3',
            'speed'            => (float) ($options['speed'] ?? 1.0),
        ];
        // Instrucciones de habla (acento, tono): solo las entienden los modelos gpt-4o; tts-1 las rechaza.
        if (! empty($options['instructions']) && str_starts_with($model, 'gpt-4o')) {
            $cuerpo['instructions'] = (string) $options['instructions'];
        }

        return $cuerpo;
    }

    private function guardar($response, string $text, string $outputPath, string $model, string $voice, array $options, $integracion): array
    {
        if (! $response instanceof Response) {
            $error = $response instanceof \Throwable ? $response->getMessage() : 'Sin respuesta del servicio de voz';
            Log::channel('marketing')->error('OpenAI TTS exception: ' . $error);
            return ['success' => false, 'error' => $error];
        }

        try {
            if ($response->status() !== 200) {
                $error = $response->json('error.message') ?? $response->body();
                Log::channel('marketing')->error("OpenAI TTS error {$response->status()}: {$error}");
                return ['success' => false, 'error' => "OpenAI TTS error {$response->status()}: {$error}"];
            }

            $dir = dirname($outputPath);
            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }

            file_put_contents($outputPath, $response->body());

            // Duración con ffprobe (los videos la usan; el bot de voz la salta: es tiempo en plena llamada).
            $duration = 0;
            if (empty($options['sin_duracion'])) {
                $info     = (new FFmpegService())->probe($outputPath);
                $duration = $info['duration'] ?? 0;
            }

            // Costo aproximado: tts-1 y gpt-4o-mini-tts ≈ $0.015 / 1000 caracteres; tts-1-hd el doble.
            $costPer1k = ($model === 'tts-1-hd') ? 0.030 : 0.015;
            $cost      = (mb_strlen($text) / 1000) * $costPer1k;
            IA::registrarUsoIntegracion($integracion, $this->claveIa, round($cost, 6));

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
