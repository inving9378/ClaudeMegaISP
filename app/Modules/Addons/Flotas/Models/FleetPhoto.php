<?php

namespace App\Modules\Addons\Flotas\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;

class FleetPhoto extends BaseModel
{
    protected $table = 'fleet_photos';

    protected $fillable = [
        'vehicle_id', 'photo_type', 'file_path', 'taken_at',
    ];

    protected $casts = [
        'taken_at' => 'datetime',
    ];

    public function vehicle()
    {
        return $this->belongsTo(FleetVehicle::class, 'vehicle_id');
    }

    public function scopeForClient(Builder $q, ?int $clientId): Builder
    {
        return $q->whereHas('vehicle', fn($v) => $clientId
            ? $v->where('client_id', $clientId)
            : $v->whereNull('client_id'));
    }
}
