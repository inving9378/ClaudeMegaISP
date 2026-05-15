<?php

namespace App\Modules\Core\Configuracion\Models;

use Illuminate\Database\Eloquent\Model;

class ApiMobileLog extends Model
{
    protected $table = 'api_mobile_logs';
    public $timestamps = false; // only `created_at`

    protected $fillable = [
        'user_id', 'method', 'endpoint', 'status', 'ip', 'duration_ms', 'created_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'status' => 'integer',
        'duration_ms' => 'integer',
        'created_at' => 'datetime',
    ];
}
