<?php

namespace App\Models\Marketing;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeadForm extends Model
{
    use SoftDeletes;

    protected $table = 'marketing_lead_forms';

    protected $fillable = [
        'company_id', 'slug', 'name', 'description', 'fields_schema', 'styling',
        'thank_you_message', 'thank_you_redirect_url', 'assign_to_source_id',
        'assign_to_user_id', 'assign_to_pipeline_id', 'auto_scoring',
        'rate_limit_per_minute', 'rate_limit_per_hour', 'recaptcha_enabled',
        'recaptcha_site_key', 'submissions_count', 'active',
    ];

    protected $casts = [
        'fields_schema' => 'array',
        'styling' => 'array',
        'auto_scoring' => 'boolean',
        'recaptcha_enabled' => 'boolean',
        'active' => 'boolean',
    ];

    public function assignToSource(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class, 'assign_to_source_id');
    }

    public function assignToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assign_to_user_id');
    }

    public function assignToPipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class, 'assign_to_pipeline_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'lead_form_id');
    }
}
