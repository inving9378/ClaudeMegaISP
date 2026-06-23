<?php

namespace App\Modules\Addons\Flotas\Models;

use App\Models\BaseModel;
use App\Models\User;
use App\Traits\BelongsToClientTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

class FleetGeofence extends BaseModel
{
    use SoftDeletes;
    use BelongsToClientTenant;

    /** Módulo interno Meganet: client_id NULL = flota propia (visible para admin). */
    protected bool $allowNullTenant = true;

    protected $table = 'fleet_geofences';

    protected $fillable = [
        'client_id', 'name', 'description', 'type', 'polygon', 'color', 'active',
    ];

    protected $casts = [
        'polygon' => 'array',
        'active'  => 'boolean',
    ];

    // ── Relaciones ────────────────────────────────────────────────────────────

    public function vehicles()
    {
        return $this->belongsToMany(
            FleetVehicle::class,
            'fleet_geofence_vehicles',
            'geofence_id',
            'vehicle_id'
        )->withTimestamps();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('active', true);
    }

    // Multi-tenancy: scopeForClient() lo provee BelongsToClientTenant
    // (null = flota interna Meganet; un id = cliente ISP dueño).

    // ── Accessors ─────────────────────────────────────────────────────────────

    /** Centroide [lat, lng] del polígono (promedio simple de vértices). */
    public function getCentroidAttribute(): ?array
    {
        $points = $this->polygon ?? [];
        if (count($points) < 3) {
            return null;
        }

        $lat = 0.0;
        $lng = 0.0;
        foreach ($points as $p) {
            $lat += (float) ($p[0] ?? 0);
            $lng += (float) ($p[1] ?? 0);
        }
        $n = count($points);

        return [round($lat / $n, 7), round($lng / $n, 7)];
    }
}
