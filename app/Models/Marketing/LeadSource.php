<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeadSource extends Model
{
    use SoftDeletes;

    protected $table = 'marketing_lead_sources';

    protected $fillable = [
        'company_id', 'code', 'name', 'description', 'color', 'icon', 'is_paid', 'active',
    ];

    protected $casts = [
        'is_paid' => 'boolean',
        'active' => 'boolean',
    ];

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'source_id');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class, 'source_id');
    }
}
