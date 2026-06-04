<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class TalentoWorkOrderActivation extends Model
{
    protected $table = 'talento_work_order_activations';
    public $timestamps = false;

    protected $fillable = [
        'work_order_id', 'activated_by', 'requested_at', 'activated_at',
        'olt_dispatched', 'olt_response', 'notes',
    ];

    protected $casts = [
        'requested_at'  => 'datetime',
        'activated_at'  => 'datetime',
        'olt_dispatched'=> 'boolean',
        'olt_response'  => 'array',
        'created_at'    => 'datetime',
    ];

    public function workOrder()
    {
        return $this->belongsTo(TalentoWorkOrder::class, 'work_order_id');
    }

    public function activatedBy()
    {
        return $this->belongsTo(User::class, 'activated_by');
    }
}
