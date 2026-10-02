<?php

namespace App\Modules\Addons\Marketing\Services;

use App\Modules\Addons\Marketing\Models\Campaign;
use App\Modules\Addons\Marketing\Models\MarketingTemplate;
use App\Modules\Addons\IA\Services\IA;
use App\Modules\Addons\IA\Services\IANoConfigurada;
use Illuminate\Support\Facades\Log;

/**
 * Copys e imagen de campañas. La IA la decide Integraciones → Módulos IA
 * (clave marketing.contenido).
 */
class AIContentService
{

    /**
     * Genera 3 variaciones de copy para una campaña usando una plantilla.
     * Devuelve array de ['index' => int, 'text' => string].
     *
     * @throws IANoConfigurada si el módulo no tiene IA asignada (el controlador avisa;
     *         no se guardan copias falsas marcadas como generadas por IA).
     */
    public function generateCopy(Campaign $campaign, MarketingTemplate $template): array
    {
        $channels = implode(', ', $campaign->channel ?? ['whatsapp']);
        $zone     = $campaign->target_zone ?? 'la zona';

        $prompt = <<<PROMPT
Eres un experto en marketing digital para un ISP (proveedor de internet) en México.

Genera exactamente 3 variaciones de mensaje de marketing para:
- Canal: {$channels}
- Zona objetivo: {$zone}
- Tipo de plantilla: {$template->template_type}

Instrucciones del sistema:
{$template->system_prompt}

Mensaje base de referencia:
{$template->base_copy}

IMPORTANTE: Responde ÚNICAMENTE con JSON válido en este formato, sin texto adicional:
{
  "variations": [
    {"index": 0, "text": "mensaje variación 1"},
    {"index": 1, "text": "mensaje variación 2"},
    {"index": 2, "text": "mensaje variación 3"}
  ]
}

Cada variación debe ser diferente en estructura y palabras pero comunicar la misma oferta.
Máximo 160 caracteres para WhatsApp. Español neutro latinoamericano, tono cercano y directo.
PROMPT;

        try {
            $r = IA::enviar('marketing.contenido', $prompt, [], null, [], [
                'max_tokens' => 1024, 'timeout' => 30, 'json' => true,
            ]);
        } catch (IANoConfigurada $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('AIContentService::generateCopy error', [
                'error'       => $e->getMessage(),
                'campaign_id' => $campaign->id,
            ]);
            return $this->fallbackVariations($template->base_copy ?? '');
        }

        $data = IA::json($r['texto']);

        return (is_array($data) && isset($data['variations']))
            ? $data['variations']
            : $this->fallbackVariations($template->base_copy ?? '');
    }

    /**
     * Genera un prompt en inglés para Stable Diffusion basado en la campaña.
     */
    public function generateImagePrompt(Campaign $campaign): string
    {
        $zone = $campaign->target_zone ?? 'Mexico';

        $prompt = <<<PROMPT
Generate a concise image generation prompt in English for a marketing campaign.
Context: ISP internet provider in {$zone}, Mexico. Promoting fast reliable home internet service.
Style: photorealistic, professional marketing photography, warm and welcoming, modern.
Output ONLY the image prompt text, nothing else, maximum 200 characters.
PROMPT;

        $porDefecto = "happy family using fast internet at home in {$zone} Mexico, modern living room, fiber optic cables, photorealistic, warm lighting";

        // Sin IA asignada o con error: prompt fijo (la imagen se sigue pudiendo generar).
        try {
            $r = IA::enviar('marketing.contenido', $prompt, [], null, [], [
                'max_tokens' => 150, 'timeout' => 15,
            ]);
            return trim($r['texto']) ?: $porDefecto;
        } catch (\Throwable $e) {
            return $porDefecto;
        }
    }

    private function fallbackVariations(string $baseCopy): array
    {
        return [
            ['index' => 0, 'text' => $baseCopy],
            ['index' => 1, 'text' => $baseCopy],
            ['index' => 2, 'text' => $baseCopy],
        ];
    }
}
