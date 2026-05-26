<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use SoftDeletes;

    protected $table = 'marketing_campaigns';

    protected $fillable = [
        'company_id', 'name', 'slug', 'type', 'source_id', 'external_id',
        'budget', 'spent', 'currency', 'start_date', 'end_date', 'status',
        'config', 'goals',
    ];

    protected $casts = [
        'config' => 'array',
        'goals' => 'array',
        'budget' => 'decimal:2',
        'spent' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class, 'source_id');
    }

    public function attributions(): HasMany
    {
        return $this->hasMany(Attribution::class);
    }

    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class);
    }

    public function generatedContent(): HasMany
    {
        return $this->hasMany(GeneratedContent::class, 'source_campaign_id');
    }
}
