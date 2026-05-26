<?php

namespace App\Models\Marketing;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAgentConfig extends Model
{
    use SoftDeletes;

    protected $table = 'marketing_ai_agent_configs';

    protected $fillable = [
        'company_id', 'name', 'channel_id', 'system_prompt', 'persona',
        'knowledge_base', 'max_turns_before_human', 'auto_assign_to_user_id',
        'model', 'temperature', 'max_tokens', 'tools_enabled', 'active',
    ];

    protected $casts = [
        'tools_enabled' => 'array',
        'active' => 'boolean',
        'temperature' => 'decimal:2',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function autoAssignToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auto_assign_to_user_id');
    }
}
