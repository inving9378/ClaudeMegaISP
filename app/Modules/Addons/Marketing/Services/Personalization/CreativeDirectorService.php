<?php

namespace App\Modules\Addons\Marketing\Services\Personalization;

use App\Models\Core\ApiIntegration;
use App\Models\Marketing\MarketingNiche;
use App\Modules\Addons\Marketing\Services\ClaudeApiClient;
use App\Services\Core\ApiIntegrationService;
use Illuminate\Support\Facades\Log;

class CreativeDirectorService
{
    protected ClaudeApiClient $claude;

    public function __construct(int $companyId = 1)
    {
        $this->claude = app(ClaudeApiClient::class);
    }

    public function generateMultivariantBriefs(array $campaignInput, int $companyId = 1): array
    {
        $niches = MarketingNiche::where('company_id', $companyId)
            ->where('active', true)
            ->orderBy('display_order')
            ->get();

        $briefs = [];
        foreach ($niches as $niche) {
            try {
                $brief               = $this->generateBriefForNiche($niche, $campaignInput, $companyId);
                $brief['niche_slug'] = $niche->slug;
                $brief['niche_id']   = $niche->id;
                $briefs[]            = $brief;
            } catch (\Throwable $e) {
                Log::channel('marketing')->error('CreativeDirector failed for niche', [
                    'niche' => $niche->slug,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $briefs;
    }

    protected function generateBriefForNiche(MarketingNiche $niche, array $campaignInput, int $companyId): array
    {
        $prompt = $this->buildPromptForNiche($niche, $campaignInput);

        $response = $this->claude->messages([
            'model'      => 'claude-opus-4-7',
            'max_tokens' => 1500,
            'system'     => $this->getSystemPrompt(),
            'messages'   => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        $this->trackApiUsage($response, $niche->slug, $companyId);

        $rawText = $response['content'][0]['text'] ?? '';
        return $this->parseBrief($rawText);
    }

    protected function getSystemPrompt(): string
    {
        return <<<'PROMPT'
Eres un director creativo experto en marketing para ISPs (proveedores de internet) en México.
Generas briefs de videos cortos (15-20 segundos) para Reels/TikTok/Stories.

Tu output SIEMPRE debe ser JSON válido, sin texto antes o después, con esta estructura exacta:

{
  "hook": "Primera frase de máximo 8 palabras que captura atención (mostrada en primeros 3 segundos)",
  "main_benefits": ["Beneficio 1 (max 4 palabras)", "Beneficio 2", "Beneficio 3"],
  "voiceover_script": "Guión de MÁXIMO 35 PALABRAS para voz en off (15 segundos). Cuenta las palabras. No excedas 35.",
  "kinetic_text_segments": [
    {"text": "TEXTO 1", "duration_sec": 1.5, "emphasis": "high"},
    {"text": "más texto", "duration_sec": 2.0, "emphasis": "medium"}
  ],
  "broll_keywords": ["palabra1","palabra2","palabra3"],
  "music_mood": "epic_electronic",
  "cta": "Llamada a acción final SIN número de teléfono. Máximo 6 palabras. Ejemplos: 'Cámbiate hoy', 'Pide tu plan ya'. El teléfono se muestra por separado.",
  "scene_sequence": ["intro_hook","problem","solution","cta"]
}

REGLAS:
- Habla el lenguaje del nicho (informal para gamer, profesional para PyME, etc.)
- El hook debe ser una sorpresa o pregunta intrigante, NO "Hola somos..."
- Cada kinetic_text_segment debe ser legible en 1-2 segundos
- El voiceover NO repite el kinetic_text, lo complementa
- Para nichos jóvenes (gamer, streamer) usa lenguaje directo, slang ligero
- Para adulto_mayor: oraciones cortas, sin tecnicismos
- NUNCA inventes datos del producto, usa los que te doy
PROMPT;
    }

    protected function buildPromptForNiche(MarketingNiche $niche, array $campaignInput): string
    {
        $motivators = implode(', ', $niche->motivators ?? []);
        $painPoints = implode(', ', $niche->pain_points ?? []);
        $vocabulary = implode(', ', $niche->vocabulary ?? []);

        $planName     = $campaignInput['plan_name']         ?? '';
        $planSpeed    = $campaignInput['plan_speed']        ?? '';
        $planPrice    = $campaignInput['plan_price']        ?? '';
        $highlight    = $campaignInput['highlight_feature'] ?? '';
        $phoneCta     = $campaignInput['phone_cta']         ?? '';

        return <<<PROMPT
Genera el brief de video para el nicho: **{$niche->name}**

# Sobre el nicho
{$niche->description}

Motivadores principales: {$motivators}
Pain points: {$painPoints}
Vocabulario que usa: {$vocabulary}
Tono emocional deseado: {$niche->emotional_tone}

# Sobre el producto a anunciar
Plan: {$planName}
Velocidad: {$planSpeed}
Precio: \${$planPrice} pesos/mes
Característica destacada: {$highlight}
Teléfono CTA: {$phoneCta}

# Tarea
Genera el brief JSON apuntando específicamente a este nicho. Habla SU lenguaje, conecta con SUS dolores, vende los beneficios que A ELLOS les importan.

Recuerda: SOLO JSON, sin markdown, sin explicación.
PROMPT;
    }

    protected function parseBrief(string $rawJson): array
    {
        $clean  = preg_replace('/^```json\s*|\s*```$/m', '', trim($rawJson));
        $parsed = json_decode($clean, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Brief inválido de Claude: ' . json_last_error_msg() . ' — raw: ' . substr($clean, 0, 200));
        }

        // Limpiar teléfonos del CTA si Claude los incluyó (patrones de 8+ dígitos)
        if (!empty($parsed['cta'])) {
            $parsed['cta'] = preg_replace('/[\+\(\)\d\s\-]{8,}/', '', $parsed['cta']);
            $parsed['cta'] = trim(preg_replace('/\s+/', ' ', $parsed['cta']));
            if (mb_strlen($parsed['cta']) < 4) {
                $parsed['cta'] = 'Llama ahora';
            }
        }

        // Truncar voiceover si excede 40 palabras (hard cap)
        if (!empty($parsed['voiceover_script'])) {
            $words = preg_split('/\s+/', trim($parsed['voiceover_script']));
            if (count($words) > 40) {
                $parsed['voiceover_script'] = implode(' ', array_slice($words, 0, 40)) . '.';
                Log::channel('marketing')->warning('[Director] Voiceover truncado', [
                    'original_words' => count($words),
                ]);
            }
        }

        return $parsed;
    }

    protected function trackApiUsage(array $response, string $nicheSlug, int $companyId): void
    {
        try {
            $usage     = $response['usage'] ?? [];
            $inputCost = ($usage['input_tokens'] ?? 0) * 15 / 1_000_000;
            $outCost   = ($usage['output_tokens'] ?? 0) * 75 / 1_000_000;
            $cost      = $inputCost + $outCost;

            $svc = app(ApiIntegrationService::class);
            $int = $svc->getIntegration('anthropic', $companyId);
            if ($int) {
                $svc->trackUsage($int, "creative_director_{$nicheSlug}", 1, $cost);
            }
        } catch (\Throwable) {}
    }
}
