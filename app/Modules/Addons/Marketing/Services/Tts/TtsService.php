<?php

namespace App\Modules\Addons\Marketing\Services\Tts;

use App\Models\Marketing\GeneratedContent;
use App\Models\Marketing\Setting;
use App\Modules\Addons\Marketing\Services\Tts\Support\SpeechTextPreprocessor;
use Illuminate\Support\Facades\Log;

class TtsService
{
    protected SpeechTextPreprocessor $preprocessor;

    public function __construct(protected int $companyId = 1)
    {
        $this->preprocessor = new SpeechTextPreprocessor();
    }

    public function getDriver(?string $name = null): TtsDriverInterface
    {
        $name = $name ?? Setting::get('tts_default_driver', $this->companyId) ?? 'openai';

        return match ($name) {
            'openai' => new OpenAiTtsDriver($this->companyId),
            'piper'  => new PiperTtsDriver($this->companyId),
            default  => throw new \InvalidArgumentException("Driver TTS desconocido: {$name}"),
        };
    }

    public function synthesize(string $text, string $outputPath, array $options = []): array
    {
        $skipPreprocessing = $options['skip_preprocessing'] ?? false;
        $textForTts        = $skipPreprocessing ? $text : $this->preprocessor->preprocess($text);

        if (!$skipPreprocessing && $text !== $textForTts) {
            Log::channel('marketing')->debug('[TTS] Preprocesamiento aplicado', [
                'original_length'  => strlen($text),
                'processed_length' => strlen($textForTts),
                'sample_original'  => substr($text, 0, 100),
                'sample_processed' => substr($textForTts, 0, 100),
            ]);
        }

        $driver = $this->getDriver($options['driver'] ?? null);
        $result = $driver->synthesize($textForTts, $outputPath, $options);

        if ($result['success'] && ($result['cost_usd'] ?? 0) > 0) {
            $this->logCost($textForTts, $result, $driver->getDriverName());
        }

        return $result;
    }

    protected function logCost(string $text, array $result, string $driverName): void
    {
        try {
            GeneratedContent::create([
                'company_id'          => $this->companyId,
                'type'                => 'audio_voiceover',
                'generation_engine'   => "tts_{$driverName}",
                'generation_cost_usd' => $result['cost_usd'],
                'generation_metadata' => [
                    'chars'        => $result['chars']        ?? 0,
                    'duration_sec' => $result['duration_sec'] ?? 0,
                    'voice'        => $result['voice']        ?? '',
                    'driver'       => $driverName,
                ],
                'status' => 'completed',
            ]);
        } catch (\Throwable $e) {
            Log::channel('marketing')->warning('TTS cost log failed: ' . $e->getMessage());
        }
    }
}
