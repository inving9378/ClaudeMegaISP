<?php

namespace App\Modules\Addons\Marketing\Services;

use App\Modules\Addons\Marketing\Models\Lead;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LeadQualifierService
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
     * Califica un lead analizando su historial de conversación con Claude.
     * Actualiza qualification_score, qualification_notes y status en el modelo.
     * Devuelve el array de resultado completo.
     */
    public function qualify(Lead $lead): array
    {
        $conversation = $this->formatConversation($lead->conversation ?? []);
        $contactName = $lead->contact_name ?? 'Desconocido';

        $prompt = <<<PROMPT
Eres un experto en ventas y calificación de prospectos para un ISP (proveedor de internet) en México.

Analiza la siguiente conversación con un prospecto y determina su nivel de interés y probabilidad de conversión.

CONVERSACIÓN:
{$conversation}

CANAL: {$lead->channel}
NOMBRE: {$contactName}

Evalúa y responde ÚNICAMENTE con JSON válido en este formato exacto:
{
  "score": 75,
  "status": "qualified",
  "notes": "Razón específica y concisa de la calificación",
  "next_action": "Acción recomendada para el agente de ventas",
  "signals": {
    "interest_level": "high",
    "budget_concern": false,
    "timeline": "immediate",
    "objections": ["lista de objeciones detectadas"]
  }
}

Reglas de puntuación (score 0-100):
- 80-100: Hot lead. Interés alto, quiere contratar pronto. Agendar instalación.
- 50-79: Warm lead. Interés moderado. Seguimiento en 24-48h.
- 20-49: Cold lead. Dudas, restricciones o poco interés. Nutrir con información.
- 0-19: No calificado. No es cliente potencial viable en este momento.

Status válidos: "new", "contacted", "qualified", "unqualified", "scheduled"
interest_level válidos: "high", "medium", "low", "unknown"
timeline válidos: "immediate", "this_week", "this_month", "future", "unknown"
PROMPT;

        $response = Http::withHeaders([
            'x-api-key'         => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])->timeout(20)->post($this->endpoint, [
            'model'      => $this->model,
            'max_tokens' => 512,
            'messages'   => [['role' => 'user', 'content' => $prompt]],
        ]);

        if (!$response->successful()) {
            Log::error('LeadQualifierService::qualify error', [
                'lead_id' => $lead->id,
                'status'  => $response->status(),
            ]);
            return $this->fallbackQualification();
        }

        $text  = (string) $response->json('content.0.text', '{}');
        $clean = trim(preg_replace('/```json|```/', '', $text));
        $data  = json_decode($clean, true);

        if (!is_array($data) || !isset($data['score'])) {
            return $this->fallbackQualification();
        }

        $lead->update([
            'qualification_score' => (int) $data['score'],
            'qualification_notes' => $data['notes'] ?? null,
            'status'              => $data['status'] ?? $lead->status,
        ]);

        return $data;
    }

    private function formatConversation(array $messages): string
    {
        if (empty($messages)) {
            return '(Sin historial de conversación)';
        }

        return collect($messages)
            ->map(fn ($m) => ucfirst($m['role'] ?? 'usuario') . ': ' . ($m['content'] ?? ''))
            ->implode("\n");
    }

    private function fallbackQualification(): array
    {
        return [
            'score'       => 0,
            'status'      => 'new',
            'notes'       => 'No se pudo calificar automáticamente. Revisión manual requerida.',
            'next_action' => 'Revisar manualmente la conversación',
            'signals'     => [
                'interest_level' => 'unknown',
                'budget_concern' => null,
                'timeline'       => 'unknown',
                'objections'     => [],
            ],
        ];
    }
}
