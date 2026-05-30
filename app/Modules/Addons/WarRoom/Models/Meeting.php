<?php

namespace App\Modules\Addons\WarRoom\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Meeting extends Model
{
    protected $table = 'warroom_meetings';

    protected $guarded = [];

    protected $casts = [
        'scheduled_at'              => 'datetime',
        'started_at'                => 'datetime',
        'ended_at'                  => 'datetime',
        'current_section_started_at'=> 'datetime',
        'settings'                  => 'array',
    ];

    // ── Scopes ──────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['in_progress', 'paused']);
    }

    public function scopeEnded($query)
    {
        return $query->where('status', 'ended');
    }

    // ── Relations ───────────────────────────────────────────────────────────────

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderator_user_id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(MeetingSection::class, 'meeting_id')->orderBy('order_position');
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(MeetingAttendee::class, 'meeting_id');
    }

    public function actionItems(): HasMany
    {
        return $this->hasMany(ActionItem::class, 'meeting_id');
    }

    // ── Helpers ─────────────────────────────────────────────────────────────────

    public function currentSection(): ?MeetingSection
    {
        if (! $this->current_section_key) {
            return null;
        }
        return $this->sections->firstWhere('section_key', $this->current_section_key);
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['in_progress', 'paused']);
    }

    public function elapsedSeconds(): int
    {
        if (! $this->started_at) {
            return 0;
        }
        $end = $this->ended_at ?? now();
        return (int) $this->started_at->diffInSeconds($end);
    }
}
