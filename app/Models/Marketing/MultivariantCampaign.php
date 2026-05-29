<?php

namespace App\Models\Marketing;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class MultivariantCampaign extends BaseModel
{
    use SoftDeletes;

    protected $table = 'marketing_multivariant_campaigns';

    protected $fillable = [
        'company_id', 'name', 'description', 'campaign_type', 'status',
        'input_data', 'creative_briefs', 'variant_content_ids',
        'total_cost_usd', 'variants_succeeded', 'variants_failed',
        'started_at', 'completed_at', 'created_by_user_id',
    ];

    protected $casts = [
        'input_data'          => 'array',
        'creative_briefs'     => 'array',
        'variant_content_ids' => 'array',
        'started_at'          => 'datetime',
        'completed_at'        => 'datetime',
        'total_cost_usd'      => 'decimal:4',
    ];

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function variants()
    {
        $ids = $this->variant_content_ids ?? [];
        if (empty($ids)) {
            return GeneratedContent::whereRaw('0=1');
        }
        return GeneratedContent::whereIn('id', $ids);
    }
}
