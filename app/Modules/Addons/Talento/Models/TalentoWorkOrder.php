<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;

class TalentoWorkOrder extends BaseModel
{
    use SoftDeletes;

    protected $table = 'talento_work_orders';

    protected $fillable = [
        'colaborador_id', 'type_id', 'points', 'is_billable',
        'assigned_by', 'client_id', 'olt_onu_id', 'status',
        'scheduled_at', 'started_at', 'completed_at', 'validated_at', 'validated_by',
        'latitude', 'longitude', 'notes',
    ];

    protected $casts = [
        'is_billable'   => 'boolean',
        'points'        => 'integer',
        'scheduled_at'  => 'datetime',
        'started_at'    => 'datetime',
        'completed_at'  => 'datetime',
        'validated_at'  => 'datetime',
        'latitude'      => 'decimal:7',
        'longitude'     => 'decimal:7',
    ];

    public function colaborador()
    {
        return $this->belongsTo(TalentoColaborador::class);
    }

    public function type()
    {
        return $this->belongsTo(TalentoWorkOrderType::class, 'type_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function validatedBy()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function workActivities()
    {
        return $this->hasMany(TalentoWorkOrderActivity::class, 'work_order_id');
    }

    public function scopeValidatedBillable($query)
    {
        return $query->where('status', 'validated')->where('is_billable', true)->where('points', '>', 0);
    }

    public function scopeForPeriod($query, $start, $end)
    {
        return $query->whereBetween('validated_at', [$start, $end]);
    }
}
