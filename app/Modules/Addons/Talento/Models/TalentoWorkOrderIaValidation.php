<?php

namespace App\Modules\Addons\Talento\Models;

use Illuminate\Database\Eloquent\Model;

class TalentoWorkOrderIaValidation extends Model
{
    protected $table = 'talento_work_order_ia_validations';
    public $timestamps = false;

    protected $fillable = [
        'work_order_id', 'validation_type', 'flags', 'raw_response',
        'overridden', 'override_reason', 'overridden_by', 'overridden_at', 'created_by',
    ];

    protected $casts = [
        'flags'          => 'array',
        'raw_response'   => 'array',
        'overridden'     => 'boolean',
        'overridden_at'  => 'datetime',
        'created_at'     => 'datetime',
    ];

    public function workOrder()
    {
        return $this->belongsTo(TalentoWorkOrder::class, 'work_order_id');
    }

    public function hasBlockingFlags(): bool
    {
        return collect($this->flags ?? [])->where('severity', 'error')->isNotEmpty();
    }
}
