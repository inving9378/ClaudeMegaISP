<?php

namespace App\Modules\Addons\Marketing\Services;

use App\Models\Marketing\GeneratedContent;
use App\Models\Marketing\Lead;
use App\Models\Marketing\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LeadScoringService
{
    private string $endpoint = 'https://api.anthropic.com/v1/messages';

    public function scoreLead(Lead $lead): object
    {
        $apiKey = env('CLAUDE_API_KEY', Setting::get('claude_api_key') ?? '');
        $model  = Setting::get('claude_model') ?? env('CLAUDE_MODEL', 'claude-opus-4-7');

        $prompt = $this->buildPrompt($lead);

        $startTime = microtime(true);

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])->timeout(30)->post($this->endpoint, [
                'model'      => $model,
                'max_tokens' => 300,
                'temperature' => 0.2,
                'messages'   => [['role' => 'user', 'content' => $prompt]],
            ]);

            $inputTokens  = $response->json('usage.input_tokens', 0);
            $outputTokens = $response->json('usage.output_tokens', 0);
            $costUsd      = ($inputTokens * 0.000015) + ($outputTokens * 0.000075);

            $this->trackCost($lead, $model, $inputTokens, $outputTokens, $costUsd);

            if (!$response->successful()) {
                Log::error('[LeadScoring] API error', ['lead_id' => $lead->id, 'status' => $response->status()]);
                return $this->failedResult();
            }

            $text  = (string) $response->json('content.0.text', '{}');
            $clean = trim(preg_replace('/```json|```/', '', $text));
            $data  = json_decode($clean, true);

            if (!is_array($data) || !isset($data['score'])) {
                Log::warning('[LeadScoring] JSON parse failed', ['lead_id' => $lead->id, 'raw' => $text]);
                return $this->failedResult();
            }

            return (object) [
                'score'  => min(100, max(0, (int) $data['score'])),
                'reason' => substr($data['reason'] ?? '', 0, 200),
                'tags'   => $data['tags'] ?? [],
            ];
        } catch (\Throwable $e) {
            Log::error('[LeadScoring] Exception', ['lead_id' => $lead->id, 'error' => $e->getMessage()]);
            return $this->failedResult();
        }
    }

    private function buildPrompt(Lead $lead): string
    {
        $sourceName = $lead->source?->name ?? 'Desconocida';
        $sourceCode = $lead->source?->code ?? '';

        return <<<PROMPT
Eres un calificador experto de leads para ISPs (proveedores de internet) en México. Analiza el siguiente lead y asigna un score 0-100 basado en:

CRITERIOS DE SCORING:
1. Calidad de datos (0-20 pts): nombre completo coherente, teléfono válido (10 dígitos MX), email válido si proporcionado, dirección con calle+número+colonia
2. Especificidad de interés (0-25 pts): mencionó velocidad específica, plan concreto, comparación con competencia, urgencia explícita
3. Cobertura potencial (0-30 pts): la dirección/colonia/ciudad parece estar en zona de cobertura típica de ISP en MX (urbano = más alto)
4. Urgencia (0-15 pts): palabras como "ya", "urgente", "hoy", "mañana", "esta semana", "me quedé sin internet", "cambio de casa"
5. Origen (0-10 pts): leads de canal pagado (meta_ads, google_ads) suelen tener más intención que orgánico

DATOS DEL LEAD:
- Nombre: {$lead->full_name}
- Email: {$lead->email}
- Teléfono: {$lead->phone}
- WhatsApp: {$lead->whatsapp}
- Dirección: {$lead->address}
- Colonia: {$lead->neighborhood}
- Ciudad: {$lead->city}, {$lead->state}
- Plan de interés: {$lead->plan_interested_id}
- Notas: {$lead->notes}
- Fuente: {$sourceName} ({$sourceCode})
- Capturado: {$lead->captured_at}

RESPONDE EXACTAMENTE este JSON sin comentarios ni markdown:
{"score":<int 0-100>,"reason":"<máx 200 caracteres en español>","tags":["<tag1>"]}

Tags posibles: "alta_intencion","urgente","datos_completos","datos_incompletos","fuera_zona","competencia_mencionada","precio_sensible","empresa","residencial"
PROMPT;
    }

    private function trackCost(Lead $lead, string $model, int $inputTokens, int $outputTokens, float $cost): void
    {
        try {
            GeneratedContent::create([
                'company_id'         => $lead->company_id,
                'type'               => 'copy',
                'source_lead_id'     => $lead->id,
                'status'             => 'used',
                'generation_engine'  => 'claude',
                'generation_cost_usd'=> $cost,
                'generation_metadata'=> ['input_tokens' => $inputTokens, 'output_tokens' => $outputTokens, 'model' => $model, 'purpose' => 'lead_scoring'],
                'generated_at'       => now(),
                'used_at'            => now(),
                'output_text'        => 'lead_scoring',
            ]);
        } catch (\Throwable $e) {
            Log::warning('[LeadScoring] Could not track cost', ['error' => $e->getMessage()]);
        }
    }

    private function failedResult(): object
    {
        return (object) ['score' => 0, 'reason' => 'scoring_failed', 'tags' => []];
    }
}
