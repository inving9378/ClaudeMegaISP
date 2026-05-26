<?php

namespace App\Models\Marketing;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use SoftDeletes;

    protected $table = 'marketing_conversations';

    protected $fillable = [
        'company_id', 'lead_id', 'channel_id', 'external_thread_id',
        'assigned_user_id', 'ai_handled', 'status', 'last_message_at',
        'last_inbound_at', 'last_outbound_at', 'unread_count', 'metadata',
    ];

    protected $casts = [
        'ai_handled' => 'boolean',
        'metadata' => 'array',
        'last_message_at' => 'datetime',
        'last_inbound_at' => 'datetime',
        'last_outbound_at' => 'datetime',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('sent_at');
    }
}
