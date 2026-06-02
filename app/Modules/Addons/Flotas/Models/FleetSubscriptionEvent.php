<?php

namespace App\Modules\Addons\Flotas\Models;

use Illuminate\Database\Eloquent\Model;

// Modelo plano (sin BaseModel/LogsActivity): es la propia bitácora de auditoría.
class FleetSubscriptionEvent extends Model
{
    public $timestamps = false;

    protected $table = 'fleet_subscription_events';

    protected $fillable = [
        'subscription_id', 'client_id', 'event_type', 'metadata', 'occurred_at', 'created_by',
    ];

    protected $casts = [
        'metadata'    => 'array',
        'occurred_at' => 'datetime',
    ];

    public function subscription()
    {
        return $this->belongsTo(FleetSubscription::class, 'subscription_id');
    }
}
