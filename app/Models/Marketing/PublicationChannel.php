<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PublicationChannel extends Model
{
    use SoftDeletes;

    protected $table = 'marketing_publication_channels';

    protected $fillable = [
        'company_id', 'platform', 'channel_type', 'name', 'slug',
        'external_id', 'external_name', 'platform_config',
        'supported_aspect_ratios', 'max_duration_seconds', 'max_file_size_mb',
        'active', 'credentials_ready', 'credentials_status_message', 'credentials_validated_at',
    ];

    protected $casts = [
        'platform_config'        => 'array',
        'supported_aspect_ratios'=> 'array',
        'active'                 => 'boolean',
        'credentials_ready'      => 'boolean',
        'credentials_validated_at'=> 'datetime',
    ];

    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class, 'pub_channel_id');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeReady($query)
    {
        return $query->where('credentials_ready', true);
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeSupportingRatio($query, string $ratio)
    {
        return $query->whereJsonContains('supported_aspect_ratios', $ratio);
    }
}
