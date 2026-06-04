<?php

namespace App\Modules\Addons\Talento\Models;

use Illuminate\Database\Eloquent\Model;

class TalentoRouteStop extends Model
{
    protected $table = 'talento_route_stops';
    public $timestamps = false;

    protected $fillable = ['route_id', 'work_order_id', 'sequence', 'eta', 'arrived_at'];

    protected $casts = ['eta' => 'datetime', 'arrived_at' => 'datetime', 'created_at' => 'datetime'];

    public function workOrder()
    {
        return $this->belongsTo(TalentoWorkOrder::class, 'work_order_id');
    }
}
