<?php

namespace App\Modules\Addons\Marketing\Services\AgentTools;

use App\Models\Marketing\Conversation;
use App\Models\Marketing\LeadActivity;

class AssignToHumanTool
{
    public static function schema(): array
    {
        return [
            'name'         => 'assign_to_human',
            'description'  => 'Escala la conversación a un agente humano. Úsala cuando: el cliente pide hablar con humano, hay queja seria, problema técnico activo, o la situación supera tu capacidad.',
            'input_schema' => [
                'type'       => 'object',
                'properties' => [
                    'reason'  => [
                        'type'        => 'string',
                        'description' => 'Razón del escalamiento (se guarda en el sistema para el agente humano).',
                    ],
                    'user_id' => [
                        'type'        => ['integer', 'null'],
                        'description' => 'ID del usuario al que asignar (opcional, si no se especifica queda sin asignar).',
                    ],
                ],
                'required' => ['reason'],
            ],
        ];
    }

    public function execute(Conversation $conversation, string $reason, ?int $userId = null): array
    {
        $meta = $conversation->metadata ?? [];
        $meta['handoff_reason'] = $reason;
        $meta['handoff_at']     = now()->toIso8601String();

        $conversation->update([
            'ai_handled'       => false,
            'status'           => 'human_review',
            'assigned_user_id' => $userId,
            'metadata'         => $meta,
        ]);

        // Log activity on the lead
        LeadActivity::create([
            'lead_id'    => $conversation->lead_id,
            'type'       => 'assigned',
            'description'=> 'Conversación escalada a agente humano: ' . $reason,
            'metadata'   => ['handoff_reason' => $reason, 'conversation_id' => $conversation->id],
            'happened_at'=> now(),
        ]);

        return [
            'success' => true,
            'message' => 'Conversación escalada a humano. Razón: ' . $reason,
        ];
    }
}
