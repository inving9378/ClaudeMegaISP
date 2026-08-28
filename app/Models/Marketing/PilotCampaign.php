<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PilotCampaign extends Model
{
    use SoftDeletes;

    protected $table = 'marketing_pilot_campaigns';

    protected $fillable = [
        'company_id', 'name', 'status',
        'variant_a_subject', 'variant_a_body', 'variant_a_cta_label', 'variant_a_cta_url',
        'variant_b_subject', 'variant_b_body', 'variant_b_cta_label', 'variant_b_cta_url',
        'batch_size', 'batch_pause_seconds',
        'dry_run_report', 'dry_run_at', 'sent_at', 'created_by_user_id',
    ];

    protected $casts = [
        'dry_run_report' => 'array',
        'dry_run_at'     => 'datetime',
        'sent_at'        => 'datetime',
    ];

    public function sends(): HasMany
    {
        return $this->hasMany(PilotCampaignSend::class, 'pilot_campaign_id');
    }

    public function variantSubject(string $variant): string
    {
        return $variant === 'b' ? $this->variant_b_subject : $this->variant_a_subject;
    }

    public function variantBody(string $variant): string
    {
        return $variant === 'b' ? $this->variant_b_body : $this->variant_a_body;
    }

    public function variantCtaLabel(string $variant): ?string
    {
        return $variant === 'b' ? $this->variant_b_cta_label : $this->variant_a_cta_label;
    }

    public function variantCtaUrl(string $variant): ?string
    {
        return $variant === 'b' ? $this->variant_b_cta_url : $this->variant_a_cta_url;
    }
}
