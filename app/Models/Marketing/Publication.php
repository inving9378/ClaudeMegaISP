<?php

namespace App\Models\Marketing;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Publication extends Model
{
    use SoftDeletes;

    protected $table = 'marketing_publications';

    protected $fillable = [
        'company_id', 'campaign_id', 'channel_id', 'content_id', 'custom_text',
        'media_paths', 'scheduled_at', 'published_at', 'status', 'external_post_id',
        'external_url', 'engagement', 'failure_reason', 'created_by_user_id',
    ];

    protected $casts = [
        'media_paths' => 'array',
        'engagement' => 'array',
        'scheduled_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(GeneratedContent::class, 'content_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
