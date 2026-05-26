<?php

namespace App\Models\Marketing;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $table = 'marketing_messages';

    protected $fillable = [
        'conversation_id', 'direction', 'content', 'content_type', 'media_paths',
        'sender', 'sender_user_id', 'external_message_id', 'reply_to_message_id',
        'sent_at', 'delivered_at', 'read_at', 'ai_intent_detected', 'ai_confidence', 'metadata',
    ];

    protected $casts = [
        'media_paths' => 'array',
        'metadata' => 'array',
        'ai_confidence' => 'decimal:3',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function senderUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'reply_to_message_id');
    }
}
