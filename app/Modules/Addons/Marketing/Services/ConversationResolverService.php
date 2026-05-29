<?php

namespace App\Modules\Addons\Marketing\Services;

use App\Models\Marketing\Channel;
use App\Models\Marketing\Conversation;
use App\Models\Marketing\Lead;
use App\Models\Marketing\LeadSource;

class ConversationResolverService
{
    private ?int $whatsappChannelId    = null;
    private ?int $whatsappDirectSourceId = null;

    public function resolveFromEvolutionMessage(array $payload, int $companyId = 1): array
    {
        $data     = $payload['data'] ?? [];
        $remoteJid = $data['key']['remoteJid'] ?? '';
        $pushName  = $data['pushName'] ?? '';

        // Normalize phone: strip @s.whatsapp.net, remove 521 prefix → 10 digits MX
        $phone = EvolutionApiService::jidToPhone($remoteJid);

        // Find or create Lead
        $isNewLead = false;
        $lead = Lead::where('company_id', $companyId)
            ->where(function ($q) use ($phone) {
                $q->where('whatsapp', $phone)->orWhere('phone', $phone);
            })
            ->first();

        if (!$lead) {
            $lead = Lead::create([
                'company_id'  => $companyId,
                'source_id'   => $this->whatsappDirectSourceId($companyId),
                'full_name'   => $pushName ?: $phone,
                'whatsapp'    => $phone,
                'phone'       => $phone,
                'status'      => 'new',
                'captured_at' => now(),
                'raw_payload' => $data,
            ]);
            $isNewLead = true;
        }

        // Find or create open Conversation
        $channelId = $this->whatsappChannelId($companyId);
        $conversation = Conversation::where('lead_id', $lead->id)
            ->where('channel_id', $channelId)
            ->whereIn('status', ['open', 'ai_handling', 'human_review'])
            ->first();

        if (!$conversation) {
            $conversation = Conversation::create([
                'company_id'         => $companyId,
                'lead_id'            => $lead->id,
                'channel_id'         => $channelId,
                'external_thread_id' => $remoteJid,
                'ai_handled'         => true,
                'status'             => 'open',
                'unread_count'       => 0,
            ]);
        }

        return [
            'lead'         => $lead,
            'conversation' => $conversation,
            'is_new_lead'  => $isNewLead,
        ];
    }

    private function whatsappChannelId(int $companyId): int
    {
        if (!$this->whatsappChannelId) {
            $this->whatsappChannelId = Channel::where('code', 'whatsapp')
                ->where('company_id', $companyId)
                ->value('id') ?? 1;
        }
        return $this->whatsappChannelId;
    }

    private function whatsappDirectSourceId(int $companyId): ?int
    {
        if (!$this->whatsappDirectSourceId) {
            $this->whatsappDirectSourceId = LeadSource::where('code', 'whatsapp_direct')
                ->where('company_id', $companyId)
                ->value('id');
        }
        return $this->whatsappDirectSourceId;
    }
}
