<?php

namespace App\Modules\Addons\Marketing\Services;

use App\Modules\Addons\Marketing\Models\Campaign;
use App\Modules\Addons\Marketing\Models\MarketingTemplate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIContentService
{
    private string $apiKey;
    private string $model;
    private string $endpoint = 'https://api.anthropic.com/v1/messages';

    public function __construct()
    {
        $this->apiKey = env('CLAUDE_API_KEY', '');
        $this->model  = config('services.anthropic.model');
    }

    /**
     * Genera 3 variaciones de copy para una campaña usando una plantilla.
     * Devuelve array de ['index' => int, 'text' => string].
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

        $response = Http::withHeaders([
            'x-api-key'         => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])->timeout(30)->post($this->endpoint, [
            'model'      => $this->model,
            'max_tokens' => 1024,
            'messages'   => [['role' => 'user', 'content' => $prompt]],
        ]);

        if (!$response->successful()) {
            Log::error('AIContentService::generateCopy error', [
                'status'      => $response->status(),
                'campaign_id' => $campaign->id,
            ]);
            return $this->fallbackVariations($template->base_copy ?? '');
        }

        $text  = (string) $response->json('content.0.text', '{}');
        $clean = trim(preg_replace('/```json|```/', '', $text));
        $data  = json_decode($clean, true);

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

        $response = Http::withHeaders([
            'x-api-key'         => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])->timeout(15)->post($this->endpoint, [
            'model'      => $this->model,
            'max_tokens' => 150,
            'messages'   => [['role' => 'user', 'content' => $prompt]],
        ]);

        if (!$response->successful()) {
            return "happy family using fast internet at home in {$zone} Mexico, modern living room, fiber optic cables, photorealistic, warm lighting";
        }

        return trim((string) $response->json('content.0.text', ''));
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
