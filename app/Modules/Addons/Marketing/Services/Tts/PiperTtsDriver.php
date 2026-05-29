<?php

namespace App\Modules\Addons\Marketing\Services\Tts;

use App\Models\Marketing\Setting;
use App\Modules\Addons\Marketing\Services\Video\FFmpegService;
use Illuminate\Support\Facades\Log;

class PiperTtsDriver implements TtsDriverInterface
{
    protected string $piperBin;
    protected string $modelPath;
    protected int    $companyId;

    public function __construct(int $companyId = 1)
    {
        $this->companyId = $companyId;
        $this->piperBin  = Setting::get('piper_binary_path', $companyId) ?? '/usr/local/bin/piper';
        $this->modelPath = Setting::get('piper_model_path',  $companyId) ?? '/opt/piper-voices/es_MX-claude-high.onnx';
    }

    public function synthesize(string $text, string $outputPath, array $options = []): array
    {
        if (!file_exists($this->piperBin)) {
            return [
                'success' => false,
                'error'   => "Piper binary not found at {$this->piperBin}. See docs/marketing/piper-setup.md",
            ];
        }

        if (!file_exists($this->modelPath)) {
            return [
                'success' => false,
                'error'   => "Piper model not found at {$this->modelPath}. See docs/marketing/piper-setup.md",
            ];
        }

        $dir = dirname($outputPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $wavPath = preg_replace('/\\.mp3$/', '.wav', $outputPath);
        $cmd     = 'echo ' . escapeshellarg($text)
                 . ' | ' . escapeshellarg($this->piperBin)
                 . ' --model ' . escapeshellarg($this->modelPath)
                 . ' --output_file ' . escapeshellarg($wavPath)
                 . ' 2>&1';

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0 || !file_exists($wavPath)) {
            Log::channel('marketing')->error('Piper TTS failed', ['cmd' => $cmd, 'output' => implode("\n", $output)]);
            return ['success' => false, 'error' => 'Piper synthesis failed: ' . implode(' ', $output)];
        }

        // Convert WAV → MP3
        $ffmpeg = new FFmpegService($this->companyId);
        $result = $ffmpeg->exec(['-i', $wavPath, '-q:a', '2', $outputPath]);
        @unlink($wavPath);

        if (!$result['success']) {
            return ['success' => false, 'error' => 'WAV→MP3 conversion failed'];
        }

        $info = $ffmpeg->probe($outputPath);
        return [
            'success'      => true,
            'duration_sec' => $info['duration'] ?? 0,
            'cost_usd'     => 0.0,
            'voice'        => 'piper',
            'chars'        => mb_strlen($text),
        ];
    }

    public function listVoices(): array
    {
        return [
            ['id' => 'es_MX-claude-high', 'name' => 'Claude (MX)', 'gender' => 'female', 'quality' => 'high'],
        ];
    }

    public function getDriverName(): string
    {
        return 'piper';
    }
}
