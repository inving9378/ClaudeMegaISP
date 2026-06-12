<?php

namespace App\Modules\Addons\VoIP\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IaBotLead extends Model
{
    protected $table = 'ia_bot_leads';

    protected $fillable = [
        'conversation_id', 'phone', 'name', 'email', 'address',
        'interest_service', 'interest_plan', 'assigned_to_user_id', 'status', 'notes',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(IaBotConversation::class, 'conversation_id');
    }
}
