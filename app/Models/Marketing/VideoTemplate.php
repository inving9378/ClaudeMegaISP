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
    ];

    protected $casts = [
        'aspect_ratios' => 'array',
        'schema' => 'array',
        'active' => 'boolean',
    ];

    public function generatedContent(): MorphMany
    {
        return $this->morphMany(GeneratedContent::class, 'template');
    }
}
