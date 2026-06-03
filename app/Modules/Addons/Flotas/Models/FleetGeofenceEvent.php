<?php

namespace App\Modules\Addons\Flotas\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Sub-fase 3.2 — Eventos de entrada/salida de geocercas.
// Model plano (sin BaseModel/LogsActivity): tabla de volumen y solo se inserta automáticamente.
class FleetGeofenceEvent extends Model
{
    protected $table = 'fleet_geofence_events';

    // Solo created_at (no updated_at); lo seteamos manualmente al insertar en batch.
    public $timestamps = false;

    protected $fillable = [
        'vehicle_id', 'geofence_id', 'event_type', 'position_id', 'occurred_at', 'created_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'created_at'  => 'datetime',
    ];

    public function geofence()
    {
        return $this->belongsTo(FleetGeofence::class, 'geofence_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(FleetVehicle::class, 'vehicle_id');
    }

    public function position()
    {
        return $this->belongsTo(FleetPosition::class, 'position_id');
    }

    public function scopeForClient(Builder $q, ?int $clientId): Builder
    {
        return $q->whereHas('vehicle', fn($v) => $v->forClient($clientId));
    }
}
