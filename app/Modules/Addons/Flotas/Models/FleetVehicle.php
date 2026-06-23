<?php

namespace App\Modules\Addons\Flotas\Models;

use App\Models\BaseModel;
use App\Traits\BelongsToClientTenant;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class FleetVehicle extends BaseModel
{
    use SoftDeletes;
    use BelongsToClientTenant;

    /** Módulo interno Meganet: client_id NULL = flota propia (visible para admin). */
    protected bool $allowNullTenant = true;

    protected $table = 'fleet_vehicles';

    protected $fillable = [
        'client_id', 'plates', 'brand', 'model', 'year', 'color',
        'vin', 'motor_number', 'vehicle_type', 'fuel_type', 'tank_capacity_liters',
        'current_km', 'status', 'habitual_location', 'notes',
        'has_gps', 'gps_brand', 'gps_model', 'gps_imei', 'gps_sim', 'gps_carrier',
    ];

    protected $casts = [
        'has_gps'              => 'boolean',
        'year'                 => 'integer',
        'current_km'           => 'integer',
        'tank_capacity_liters' => 'decimal:1',
    ];

    // ── Relaciones ────────────────────────────────────────────────────────────

    public function assignments()
    {
        return $this->hasMany(FleetAssignment::class, 'vehicle_id');
    }

    public function currentAssignment()
    {
        return $this->hasOne(FleetAssignment::class, 'vehicle_id')->whereNull('until');
    }

    public function maintenances()
    {
        return $this->hasMany(FleetMaintenance::class, 'vehicle_id')->orderByDesc('service_date');
    }

    public function documents()
    {
        return $this->hasMany(FleetDocument::class, 'vehicle_id');
    }

    public function fuelLog()
    {
        return $this->hasMany(FleetFuelLog::class, 'vehicle_id')->orderByDesc('refuel_date');
    }

    public function photos()
    {
        return $this->hasMany(FleetPhoto::class, 'vehicle_id');
    }

    // ── GPS (Fase 2) ────────────────────────────────────────────────────────

    public function device()
    {
        return $this->hasOne(FleetDevice::class, 'vehicle_id')->whereIn('status', ['active', 'no_signal']);
    }

    public function positions()
    {
        return $this->hasMany(FleetPosition::class, 'vehicle_id');
    }

    public function lastPosition()
    {
        return $this->hasOne(FleetPosition::class, 'vehicle_id')->latestOfMany('recorded_at');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────
    // scopeForClient() lo provee el trait BelongsToClientTenant (allowNullTenant=true).

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', 'active');
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function getDisplayNameAttribute(): string
    {
        return "{$this->brand} {$this->model}" . ($this->plates ? " ({$this->plates})" : '');
    }

    public function getNextServiceDaysAttribute(): ?int
    {
        $next = $this->maintenances()
            ->whereNotNull('next_service_date')
            ->orderBy('next_service_date')
            ->first();

        return $next ? Carbon::parse($next->next_service_date)->diffInDays(now(), false) * -1 : null;
    }

    public function getExpiringDocumentsCountAttribute(): int
    {
        return $this->documents()
            ->whereNotNull('expiration_date')
            ->where('expiration_date', '>=', now())
            ->where('expiration_date', '<=', now()->addDays(30))
            ->count();
    }

    public function getExpiredDocumentsCountAttribute(): int
    {
        return $this->documents()
            ->whereNotNull('expiration_date')
            ->where('expiration_date', '<', now())
            ->count();
    }
}
