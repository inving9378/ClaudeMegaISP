<?php

namespace App\Modules\Addons\Talento\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class TalentoWorkOrderMedia extends Model
{
    protected $table = 'talento_work_order_media';
    public $timestamps = false;

    protected $fillable = [
        'work_order_id', 'type', 'file_path', 'captured_lat', 'captured_lng',
        'captured_at', 'captured_in_app', 'watermark_applied',
        'location_flagged', 'location_distance_m', 'created_by',
    ];

    protected $casts = [
        'captured_lat'       => 'decimal:7',
        'captured_lng'       => 'decimal:7',
        'captured_at'        => 'datetime',
        'captured_in_app'    => 'boolean',
        'watermark_applied'  => 'boolean',
        'location_flagged'   => 'boolean',
        'created_at'         => 'datetime',
    ];

    /** Types that must have their file_path encrypted */
    public const SENSITIVE_TYPES = ['ine_front', 'ine_back', 'proof_address'];

    public function isSensitive(): bool
    {
        return in_array($this->type, self::SENSITIVE_TYPES);
    }

    public function workOrder()
    {
        return $this->belongsTo(TalentoWorkOrder::class, 'work_order_id');
    }
}
