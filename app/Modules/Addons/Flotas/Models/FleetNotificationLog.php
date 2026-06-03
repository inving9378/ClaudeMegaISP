<?php

namespace App\Modules\Addons\Flotas\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

// Model plano (sin BaseModel/LogsActivity): tabla de auditoría de alto volumen, solo created_at.
class FleetNotificationLog extends Model
{
    public $timestamps = false;

    protected $table = 'fleet_notification_log';

    protected $fillable = [
        'event_id', 'user_id', 'channel', 'destination', 'status', 'error_message', 'sent_at', 'created_at',
    ];

    protected $casts = [
        'sent_at'    => 'datetime',
        'created_at' => 'datetime',
    ];

    public function event()
    {
        return $this->belongsTo(FleetGeofenceEvent::class, 'event_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
