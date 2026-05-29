<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ContentTemplate extends Model
{
    use SoftDeletes;

    protected $table = 'marketing_content_templates';

    protected $fillable = [
        'company_id', 'name', 'category', 'language', 'template_text',
        'variables', 'use_ai', 'ai_prompt_template', 'tone', 'active',
    ];

    protected $casts = [
        'variables' => 'array',
        'use_ai' => 'boolean',
        'active' => 'boolean',
    ];

    public function generatedContent(): MorphMany
    {
        return $this->morphMany(GeneratedContent::class, 'template');
    }
}
