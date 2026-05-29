<?php

namespace App\Models\Core;

use Illuminate\Database\Eloquent\Model;

class ApiIntegrationLog extends Model
{
    protected $table = 'api_integration_logs';

    protected $fillable = [
        'integration_id', 'company_id', 'event_type', 'actor', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function integration()
    {
        return $this->belongsTo(ApiIntegration::class, 'integration_id');
    }
}
