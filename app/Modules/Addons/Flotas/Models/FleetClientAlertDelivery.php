<?php

namespace App\Modules\Addons\Flotas\Models;

use Illuminate\Database\Eloquent\Model;

/** Bitácora de envíos de alertas al cliente (append-only, sin updated_at). */
class FleetClientAlertDelivery extends Model
{
    public $timestamps = false;

    protected $table = 'fleet_client_alert_deliveries';

    protected $guarded = [];

    protected $casts = ['created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(fn ($m) => $m->created_at ??= now());
    }

    public function contact()
    {
        return $this->belongsTo(FleetClientAlertContact::class, 'contact_id');
    }
}
