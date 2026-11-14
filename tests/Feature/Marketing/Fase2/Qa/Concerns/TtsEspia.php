<?php

namespace Tests\Feature\Marketing\Fase2\Qa\Concerns;

use App\Modules\Addons\Marketing\Services\Tts\TtsService;

/** TTS que no llama a nadie: registra texto y opciones y responde «sin voz». */
class TtsEspia extends TtsService
{
    /** @var array<int, array{texto:string, opciones:array}> */
    public array $llamadas = [];

    public function synthesize(string $text, string $outputPath, array $options = []): array
    {
        $this->llamadas[] = ['texto' => $text, 'opciones' => $options];

        return ['success' => false, 'error' => 'QA: TTS espía (sin voz)'];
    }
}
