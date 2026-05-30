<?php

namespace App\Modules\Addons\WarRoom\Models;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActionItem extends Model
{
    protected $table = 'warroom_action_items';

    protected $guarded = [];

    protected $casts = [
        'deadline' => 'date',
    ];

    // ── Scopes ──────────────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeForSection($query, string $sectionKey)
    {
        return $query->where('section_key', $sectionKey);
    }

    // ── Relations ───────────────────────────────────────────────────────────────

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class, 'meeting_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }

    public function linkedTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'linked_task_id');
    }
}
