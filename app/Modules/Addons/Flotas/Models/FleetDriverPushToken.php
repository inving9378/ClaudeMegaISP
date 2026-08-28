<?php

namespace App\Modules\Addons\Flotas\Models;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;

// Item #101 (Fase 5.5) — token de push del dispositivo del conductor.
// Solo almacena el token; el envío real vía FCM depende del item #72 (Firebase greenfield).
class FleetDriverPushToken extends BaseModel
{
    use SoftDeletes;

    protected $table = 'fleet_driver_push_tokens';

    protected $fillable = [
        'user_id', 'token', 'platform', 'last_seen_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
