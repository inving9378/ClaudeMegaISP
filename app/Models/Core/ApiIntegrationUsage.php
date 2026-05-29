<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;

class ApiIntegrationUsage extends Model
{
    protected $table = 'api_integration_usage';

    protected $fillable = [
        'integration_id', 'company_id', 'usage_date', 'feature', 'call_count', 'cost_usd',
    ];

    protected $casts = [
        'usage_date' => 'date',
        'cost_usd'   => 'decimal:6',
    ];

    public function integration()
    {
        return $this->belongsTo(ApiIntegration::class, 'integration_id');
    }
}
