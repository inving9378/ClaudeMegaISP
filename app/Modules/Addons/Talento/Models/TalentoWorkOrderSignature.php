<?php

namespace App\Modules\Addons\Talento\Models;

use Illuminate\Database\Eloquent\Model;

class TalentoWorkOrderSignature extends Model
{
    protected $table = 'talento_work_order_signatures';
    public $timestamps = false;

    protected $fillable = [
        'work_order_id', 'signer_type', 'signature_path',
        'signed_lat', 'signed_lng', 'signed_at', 'created_by',
    ];

    protected $casts = [
        'signed_lat' => 'decimal:7',
        'signed_lng' => 'decimal:7',
        'signed_at'  => 'datetime',
        'created_at' => 'datetime',
    ];

    public function workOrder()
    {
        return $this->belongsTo(TalentoWorkOrder::class, 'work_order_id');
    }
}
