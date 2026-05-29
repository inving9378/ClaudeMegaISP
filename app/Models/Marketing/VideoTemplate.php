<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class VideoTemplate extends Model
{
    use SoftDeletes;

    protected $table = 'marketing_video_templates';

    protected $fillable = [
        'company_id', 'name', 'slug', 'description', 'category',
        'aspect_ratios', 'duration_seconds', 'schema', 'preview_thumbnail', 'active',
        'engine_version', 'default_variables', 'estimated_render_seconds', 'requires_voiceover',
    ];

    protected $casts = [
        'aspect_ratios'            => 'array',
        'schema'                   => 'array',
        'default_variables'        => 'array',
        'active'                   => 'boolean',
        'requires_voiceover'       => 'boolean',
        'estimated_render_seconds' => 'integer',
    ];

    public function generatedContent(): MorphMany
    {
        return $this->morphMany(GeneratedContent::class, 'template');
    }
}
