<?php

namespace App\Http\Resources\Marketing;

use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray($request): array
    {
        $lastMsg = $this->messages()->orderByDesc('sent_at')->first();

        return [
            'id'                 => $this->id,
            'status'             => $this->status,
            'ai_handled'         => (bool) $this->ai_handled,
            'unread_count'       => (int) $this->unread_count,
            'last_message_at'    => $this->last_message_at,
            'last_inbound_at'    => $this->last_inbound_at,
            'last_outbound_at'   => $this->last_outbound_at,
            'external_thread_id' => $this->external_thread_id,
            'metadata'           => $this->metadata,
            'created_at'         => $this->created_at,
            'lead'               => $this->whenLoaded('lead', fn () => [
                'id'        => $this->lead->id,
                'full_name' => $this->lead->full_name,
                'phone'     => $this->lead->phone,
                'whatsapp'  => $this->lead->whatsapp,
                'score'     => $this->lead->score,
                'status'    => $this->lead->status,
            ]),
            'channel'            => $this->whenLoaded('channel', fn () => [
                'id'   => $this->channel->id,
                'code' => $this->channel->code,
                'name' => $this->channel->name,
            ]),
            'assigned_user'      => $this->whenLoaded('assignedUser', fn () => $this->assignedUser ? [
                'id'         => $this->assignedUser->id,
                'name'       => $this->assignedUser->name,
                'login_user' => $this->assignedUser->login_user,
            ] : null),
            'last_message'       => $lastMsg ? [
                'id'         => $lastMsg->id,
                'direction'  => $lastMsg->direction,
                'content'    => $lastMsg->content,
                'sent_at'    => $lastMsg->sent_at,
                'sender'     => $lastMsg->sender,
            ] : null,
        ];
    }
}
