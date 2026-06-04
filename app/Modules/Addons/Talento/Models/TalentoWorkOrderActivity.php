<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class TalentoWorkOrderActivity extends Model
{
    protected $table = 'talento_work_order_activities';
    public $timestamps = false;

    protected $fillable = ['work_order_id', 'description', 'duration_minutes', 'recorded_by'];

    protected $casts = ['created_at' => 'datetime'];

    public function workOrder()
    {
        return $this->belongsTo(TalentoWorkOrder::class, 'work_order_id');
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
