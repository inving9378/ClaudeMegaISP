<?php

namespace App\Modules\Addons\Marketing\Services\Tts;

interface TtsDriverInterface
{
    public function synthesize(string $text, string $outputPath, array $options = []): array;
    public function listVoices(): array;
    public function getDriverName(): string;
}
