<?php

namespace App\Modules\Addons\WhatsAppAgent\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppMessage extends BaseModel
{
    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'conversation_id',
        'instance_id',
        'direction',
        'message_type',
        'body',
        'media_url',
        'media_filename',
        'media_mime_type',
        'media_size',
        'evolution_message_id',
        'quoted_message_id',
        'status',
        'error_message',
        'context',
        'sent_at',
        'delivered_at',
        'read_at',
    ];

    protected $casts = [
        'context'       => 'array',
        'sent_at'       => 'datetime',
        'delivered_at'  => 'datetime',
        'read_at'       => 'datetime',
        'media_size'    => 'integer',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConversation::class, 'conversation_id');
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WhatsAppInstance::class, 'instance_id');
    }

    public function quotedMessage(): BelongsTo
    {
        return $this->belongsTo(self::class, 'quoted_message_id');
    }

    public function scopeIncoming($query)
    {
        return $query->where('direction', 'in');
    }

    public function scopeOutgoing($query)
    {
        return $query->where('direction', 'out');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
