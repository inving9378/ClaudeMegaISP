<?php

namespace App\Modules\Addons\MonitoreoOficial\Models;

use Illuminate\Database\Eloquent\Model;

class MonitoringSource extends Model
{
    protected $table = 'monitoring_sources';

    protected $fillable = [
        'authority', 'name', 'source_type', 'base_url', 'adapter_class',
        'status', 'configuration', 'last_check_at', 'last_success_at', 'failure_count',
    ];

    protected $casts = [
        'configuration' => 'array',
        'last_check_at' => 'datetime',
        'last_success_at' => 'datetime',
        'failure_count' => 'integer',
    ];
}
