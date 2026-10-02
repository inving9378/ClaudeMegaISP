<?php

namespace App\Modules\Addons\Marketing\Services;

use App\Models\Marketing\GeneratedContent;
use App\Models\Marketing\Lead;
use App\Modules\Addons\IA\Services\IA;
use App\Modules\Addons\IA\Services\IANoConfigurada;
use App\Modules\Addons\IA\Services\IAPricingService;
use Illuminate\Support\Facades\Log;

/**
 * Puntaje de leads. La IA la decide Integraciones → Módulos IA (marketing.lead_scoring).
 */
class LeadScoringService
{
    /**
     * @return object|null {score, reason, tags}; null si el módulo no tiene IA asignada
     *         (el lead se queda sin puntuar en vez de recibir un 0 falso).
     */
    public function scoreLead(Lead $lead): ?object
    {
        $prompt = $this->buildPrompt($lead);

        try {
            $r = IA::enviar('marketing.lead_scoring', $prompt, [], null, [], [
                'max_tokens' => 300, 'temperatura' => 0.2, 'timeout' => 30, 'json' => true,
            ]);

            $this->trackCost($lead, $r);

            $data = IA::json($r['texto']);

            if (!is_array($data) || !isset($data['score'])) {
                Log::warning('[LeadScoring] JSON parse failed', ['lead_id' => $lead->id, 'raw' => $r['texto']]);
                return $this->failedResult();
            }

            return (object) [
                'score'  => min(100, max(0, (int) $data['score'])),
                'reason' => substr($data['reason'] ?? '', 0, 200),
                'tags'   => $data['tags'] ?? [],
            ];
        } catch (IANoConfigurada $e) {
            Log::info('[LeadScoring] sin IA asignada', ['lead_id' => $lead->id, 'motivo' => $e->getMessage()]);
            return null;
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

    private function trackCost(Lead $lead, array $r): void
    {
        try {
            $in  = (int) ($r['tokens_input'] ?? 0);
            $out = (int) ($r['tokens_output'] ?? 0);

            GeneratedContent::create([
                'company_id'         => $lead->company_id,
                'type'               => 'copy',
                'source_lead_id'     => $lead->id,
                'status'             => 'used',
                'generation_engine'  => $r['driver'],
                'generation_cost_usd'=> app(IAPricingService::class)->calcularCosto((string) $r['modelo'], $in, $out),
                'generation_metadata'=> ['input_tokens' => $in, 'output_tokens' => $out, 'model' => $r['modelo'], 'provider' => $r['proveedor'], 'purpose' => 'lead_scoring'],
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
