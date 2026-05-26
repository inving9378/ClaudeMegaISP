<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PipelineStage extends Model
{
    use SoftDeletes;

    protected $table = 'marketing_pipeline_stages';

    protected $fillable = [
        'pipeline_id', 'name', 'slug', 'color', 'order', 'is_won', 'is_lost',
        'description', 'default_score',
    ];

    protected $casts = [
        'is_won' => 'boolean',
        'is_lost' => 'boolean',
    ];

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function leadPipelines(): HasMany
    {
        return $this->hasMany(LeadPipeline::class, 'stage_id');
    }
}
