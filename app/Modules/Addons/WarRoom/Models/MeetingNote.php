<?php

namespace App\Modules\Addons\WarRoom\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingNote extends Model
{
    protected $table    = 'warroom_meeting_notes';
    protected $guarded  = [];
    protected $casts    = ['created_at' => 'datetime', 'updated_at' => 'datetime'];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class, 'meeting_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(MeetingSection::class, 'section_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
