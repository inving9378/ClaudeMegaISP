<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class GeneratedContent extends Model
{
    use SoftDeletes;

    protected $table = 'marketing_generated_content';

    protected $fillable = [
        'company_id', 'type', 'template_id', 'template_type', 'source_lead_id',
        'source_campaign_id', 'source_plan_id', 'input_variables', 'output_path',
        'thumbnail_path', 'output_url', 'output_text', 'status', 'generation_engine',
        'generation_cost_usd', 'generation_metadata', 'failure_reason',
        'generated_at', 'used_at', 'render_progress', 'render_stage', 'render_log',
    ];

    protected $casts = [
        'input_variables'    => 'array',
        'generation_metadata'=> 'array',
        'render_log'         => 'array',
        'generation_cost_usd'=> 'decimal:4',
        'render_progress'    => 'integer',
        'generated_at'       => 'datetime',
        'used_at'            => 'datetime',
    ];

    public function template(): MorphTo
    {
        return $this->morphTo();
    }

    public function sourceLead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'source_lead_id');
    }

    public function sourceCampaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'source_campaign_id');
    }

    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class, 'content_id');
    }
}
