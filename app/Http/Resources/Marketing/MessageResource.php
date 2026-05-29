<?php

namespace App\Http\Resources\Marketing;

use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                  => $this->id,
            'conversation_id'     => $this->conversation_id,
            'direction'           => $this->direction,
            'content'             => $this->content,
            'content_type'        => $this->content_type,
            'sender'              => $this->sender,
            'external_message_id' => $this->external_message_id,
            'sent_at'             => $this->sent_at,
            'created_at'          => $this->created_at,
            'sender_user'         => $this->whenLoaded('senderUser', fn () => $this->senderUser ? [
                'id'   => $this->senderUser->id,
                'name' => $this->senderUser->name,
            ] : null),
        ];
    }
}
