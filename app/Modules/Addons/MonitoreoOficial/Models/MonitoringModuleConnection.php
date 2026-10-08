<?php

namespace App\Modules\Addons\MonitoreoOficial\Models;

use Illuminate\Database\Eloquent\Model;

class MonitoringModuleConnection extends Model
{
    protected $table = 'monitoring_module_connections';

    protected $fillable = [
        'consumer_module', 'provider_module', 'capability', 'contract_version',
        'status', 'connected_at', 'last_checked_at', 'disconnected_at', 'error_message', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'connected_at' => 'datetime',
        'last_checked_at' => 'datetime',
        'disconnected_at' => 'datetime',
    ];
}
