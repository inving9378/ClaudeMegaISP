<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pipeline extends Model
{
    use SoftDeletes;

    protected $table = 'marketing_pipelines';

    protected $fillable = [
        'company_id', 'name', 'slug', 'description', 'is_default', 'active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'active' => 'boolean',
    ];

    public function stages(): HasMany
    {
        return $this->hasMany(PipelineStage::class)->orderBy('order');
    }

    public function leads(): BelongsToMany
    {
        return $this->belongsToMany(Lead::class, 'marketing_lead_pipeline')
            ->withPivot(['stage_id', 'position', 'entered_stage_at', 'exited_stage_at'])
            ->withTimestamps();
    }
}
