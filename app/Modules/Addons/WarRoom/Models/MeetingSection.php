<?php

namespace App\Modules\Addons\WarRoom\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingSection extends Model
{
    protected $table = 'warroom_meeting_sections';

    protected $guarded = [];

    protected $casts = [
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    // ── Relations ───────────────────────────────────────────────────────────────

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class, 'meeting_id');
    }

    public function presenter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'presenter_user_id');
    }

    // ── Helpers ─────────────────────────────────────────────────────────────────

    public function progressPercent(): float
    {
        if ($this->time_planned_seconds <= 0) {
            return 0;
        }
        return round(($this->time_actual_seconds / $this->time_planned_seconds) * 100, 1);
    }

    public function isOvertime(): bool
    {
        return $this->time_actual_seconds > $this->time_planned_seconds;
    }
}
