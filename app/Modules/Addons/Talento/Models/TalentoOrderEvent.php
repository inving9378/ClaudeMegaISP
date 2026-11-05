<?php

namespace App\Modules\Addons\Talento\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

// NO extiende BaseModel a propósito: historial append-only (UNI-T E2). Solo se inserta, y la
// única vía de escritura es App\Modules\Addons\Talento\Services\TalentoOrderEventRecorder.
class TalentoOrderEvent extends Model
{
    protected $table = 'talento_order_events';

    const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = [
        'datos'      => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        $bloquear = function () {
            throw new LogicException('Los eventos de orden son append-only: no se editan ni se borran.');
        };
        static::updating($bloquear);
        static::deleting($bloquear);
    }

    public function scopeDeOrden($query, string $origen, int $ordenId)
    {
        return $query->where('origen', $origen)->where('orden_id', $ordenId)->orderBy('id');
    }
}
