<?php

namespace Tests\Feature\Marketing\Fase2\Qa\Concerns;

use App\Models\Marketing\Asset;
use App\Modules\Addons\Marketing\Services\Tts\TtsService;
use App\Modules\Addons\Marketing\Services\Video\AssetLibraryService;
use App\Modules\Addons\Marketing\Services\Video\FFmpegService;
use App\Modules\Addons\Marketing\Services\Video\VideoTemplateRenderer;

/**
 * QA · Marketing Fase 2 — espía del motor de Video MVM (contrato §2.6, criterio 12).
 *
 * Registra QUÉ escenas pide dibujar el renderer (tipo, duración y la escena con sus variables ya resueltas),
 * qué texto y opciones manda al TTS y qué música busca, SIN dibujar nada: `renderScene` devuelve true sin
 * archivo, así que el render termina en «Scene concatenation failed» después de haber pedido TODAS las escenas.
 *
 * Con esto se compara la plantilla genérica y la de nicho contra lo que hacía el motor en la base
 * (`fixtures/escenas-base.json`, capturado con el código de 08d58e7e3 antes de que backend tocara el motor).
 */
class RendererEspia extends VideoTemplateRenderer
{
    /** @var array<int, array{type:string, duration:float, escena:array}> */
    public array $escenas = [];

    protected function renderScene(array $scene, float $duration, string $outputPath, array $context, int $width, int $height): bool
    {
        $resuelta = json_decode($this->resolveVariables(json_encode($scene, JSON_UNESCAPED_UNICODE), $context), true);
        $this->escenas[] = [
            'type' => (string) ($scene['type'] ?? 'info_banner'),
            'duration' => round($duration, 3),
            'escena' => is_array($resuelta) ? $resuelta : $scene,
            'tamano' => "{$width}x{$height}",
        ];

        return true;
    }

    public static function crear(TtsEspia $tts, ?AssetsEspia $assets = null): self
    {
        return new self(new FFmpegService(1), $tts, $assets ?? new AssetsEspia(1));
    }
}
