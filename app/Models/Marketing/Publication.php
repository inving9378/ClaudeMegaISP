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
        'company_id', 'campaign_id', 'channel_id', 'pub_channel_id', 'content_id',
        'custom_text', 'caption', 'hashtags', 'platform_options',
        'media_paths', 'scheduled_at', 'scheduled_for', 'published_at',
        'status', 'external_post_id', 'external_url', 'external_post_url',
        'engagement', 'metrics', 'metrics_updated_at',
        'failure_reason', 'retry_count', 'next_retry_at',
        'ab_variant_tag', 'created_by_user_id',
    ];

    protected $casts = [
        'media_paths'       => 'array',
        'hashtags'          => 'array',
        'platform_options'  => 'array',
        'engagement'        => 'array',
        'metrics'           => 'array',
        'scheduled_at'      => 'datetime',
        'scheduled_for'     => 'datetime',
        'published_at'      => 'datetime',
        'metrics_updated_at'=> 'datetime',
        'next_retry_at'     => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function pubChannel(): BelongsTo
    {
        return $this->belongsTo(PublicationChannel::class, 'pub_channel_id');
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(GeneratedContent::class, 'content_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function logs()
    {
        return $this->hasMany(PublicationLog::class, 'publication_id');
    }

    public function addLog(string $event, string $message, array $payload = []): void
    {
        $this->logs()->create(compact('event', 'message', 'payload'));
    }
}
