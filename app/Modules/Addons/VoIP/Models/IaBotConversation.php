<?php

namespace App\Modules\Addons\VoIP\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class IaBotConversation extends Model
{
    protected $table = 'ia_bot_conversations';

    protected $fillable = [
        'call_id', 'phone_number', 'customer_id', 'customer_name', 'customer_email',
        'conversation_type', 'started_at', 'ended_at', 'duration', 'transcript',
        'problem_category', 'resolved', 'escalated', 'escalated_to',
        'transferred_at', 'transferred_extension', 'lead_data', 'cost_usd', 'status',
    ];

    protected $casts = [
        'transcript' => 'array',
        'lead_data'  => 'array',
        'resolved'   => 'boolean',
        'escalated'  => 'boolean',
        'started_at' => 'datetime',
        'ended_at'   => 'datetime',
        'transferred_at' => 'datetime',
    ];

    public function lead(): HasOne
    {
        return $this->hasOne(IaBotLead::class, 'conversation_id');
    }
}
