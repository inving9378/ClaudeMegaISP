<?php

namespace App\Modules\Addons\Talento\Models;

use Illuminate\Database\Eloquent\Model;

class TalentoRouteDeviation extends Model
{
    protected $table = 'talento_route_deviations';
    public $timestamps = false;

    protected $fillable = [
        'route_id', 'colaborador_id', 'detected_lat', 'detected_lng',
        'deviation_m', 'sustained_minutes', 'supervisor_notified',
    ];

    protected $casts = [
        'detected_lat'       => 'decimal:7',
        'detected_lng'       => 'decimal:7',
        'deviation_m'        => 'decimal:1',
        'supervisor_notified'=> 'boolean',
        'detected_at'        => 'datetime',
    ];
}
