<?php

namespace App\Modules\Addons\Marketing\Services;

use App\Models\Marketing\AiAgentConfig;
use App\Models\Marketing\Conversation;
use App\Models\Marketing\Lead;
use App\Models\Marketing\Message;

class PromptBuilder
{
    public function buildSystemPrompt(AiAgentConfig $config, Lead $lead, Conversation $conversation): string
    {
        $tags        = is_array($lead->tags) ? implode(', ', $lead->tags) : ($lead->tags ?? '—');
        $leadContext = <<<TXT
[CONTEXTO DEL LEAD]
- Nombre: {$this->v($lead->full_name)}
- Teléfono: {$this->v($lead->phone)}
- WhatsApp: {$this->v($lead->whatsapp)}
- Email: {$this->v($lead->email)}
- Dirección: {$this->v($lead->address)}
- Colonia: {$this->v($lead->neighborhood)}
- Ciudad: {$this->v($lead->city)}
- Estado: {$this->v($lead->state)}
- Plan de interés: {$this->v($lead->notes)}
- Score: {$lead->score}/100 {$this->v($lead->score_reason, '(sin calificación)')}
- Tags: {$this->v($tags)}
- Estatus: {$lead->status}
TXT;

        $rules = <<<'TXT'
[REGLAS DE COMPORTAMIENTO]
1. Responde SIEMPRE en español mexicano natural y amable.
2. NUNCA inventes precios, planes o información. Si no sabes, usa query_plans o check_coverage.
3. Si el cliente quiere agendar instalación o visita, usa schedule_appointment.
4. Si detectas frustración, queja seria, o petición fuera de tu alcance, usa assign_to_human inmediatamente.
5. Conforme obtengas datos nuevos del lead, usa update_lead para guardarlos.
6. Sé conciso: WhatsApp se lee rápido. Máximo 2-3 párrafos por mensaje.
7. NO uses formato markdown (negritas con **, listas con -, etc.). WhatsApp no las renderiza.
8. Usa saltos de línea simples para separar ideas. Emojis con moderación.
TXT;

        return implode("\n\n", array_filter([
            '[PERSONA]',
            $config->persona,
            '[BASE DE CONOCIMIENTO]',
            $config->knowledge_base,
            $leadContext,
            $rules,
        ]));
    }

    /**
     * Build messages array for Claude from conversation history.
     * @return array [{role: user|assistant, content: string}, ...]
     */
    public function buildMessages(Conversation $conversation, int $limit = 20): array
    {
        $messages = Message::where('conversation_id', $conversation->id)
            ->whereIn('content_type', ['text'])
            ->orderBy('sent_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        return $messages->map(function (Message $msg) {
            return [
                'role'    => $msg->direction === 'inbound' ? 'user' : 'assistant',
                'content' => $msg->content ?? '',
            ];
        })->filter(fn ($m) => !empty($m['content']))->values()->toArray();
    }

    private function v(?string $val, string $fallback = 'No registrado'): string
    {
        return (!empty($val) && $val !== '[]') ? $val : $fallback;
    }
}
