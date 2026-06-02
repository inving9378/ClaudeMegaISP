<?php

namespace App\Modules\Addons\Flotas\Models;

use App\Models\BaseModel;
use App\Modules\Addons\Flotas\Support\FleetPlans;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class FleetSubscription extends BaseModel
{
    use SoftDeletes;

    protected $table = 'fleet_subscriptions';

    protected $fillable = [
        'client_id', 'plan', 'status',
        'trial_starts_at', 'trial_ends_at', 'trial_notified_days',
        'started_at', 'cancelled_at', 'cancel_reason',
        'vehicles_count', 'price_per_vehicle', 'monthly_price',
        'next_billing_date', 'last_billed_at', 'auto_renew',
        'data_retention_until', 'notes',
    ];

    protected $casts = [
        'trial_starts_at'      => 'datetime',
        'trial_ends_at'        => 'datetime',
        'started_at'           => 'datetime',
        'cancelled_at'         => 'datetime',
        'next_billing_date'    => 'date',
        'last_billed_at'       => 'date',
        'data_retention_until' => 'date',
        'auto_renew'           => 'boolean',
        'vehicles_count'       => 'integer',
        'trial_notified_days'  => 'integer',
        'price_per_vehicle'    => 'decimal:2',
        'monthly_price'        => 'decimal:2',
    ];

    protected $appends = ['plan_name', 'is_usable', 'trial_days_left'];

    // ── Relaciones ────────────────────────────────────────────────────────────

    public function events()
    {
        return $this->hasMany(FleetSubscriptionEvent::class, 'subscription_id')->orderByDesc('occurred_at');
    }

    public function vehicles()
    {
        return $this->hasMany(FleetVehicle::class, 'client_id', 'client_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeForClient(Builder $q, ?int $clientId): Builder
    {
        return $q->where('client_id', $clientId);
    }

    /** Suscripciones que dan acceso al módulo (trial o pagando). */
    public function scopeUsable(Builder $q): Builder
    {
        return $q->whereIn('status', ['trial', 'active', 'past_due']);
    }

    // ── Helpers de estado ───────────────────────────────────────────────────────

    /** ¿La suscripción permite usar el módulo ahora mismo? */
    public function isUsable(): bool
    {
        if (in_array($this->status, ['cancelled', 'expired'], true)) {
            return false;
        }
        if ($this->status === 'trial' && $this->trial_ends_at) {
            return Carbon::parse($this->trial_ends_at)->isFuture();
        }
        return true;
    }

    public function getIsUsableAttribute(): bool
    {
        return $this->isUsable();
    }

    public function getPlanNameAttribute(): string
    {
        return FleetPlans::get($this->plan)['name'] ?? $this->plan;
    }

    /** Días restantes de trial (negativo si ya venció, null si no aplica). */
    public function getTrialDaysLeftAttribute(): ?int
    {
        if ($this->status !== 'trial' || ! $this->trial_ends_at) {
            return null;
        }
        return (int) ceil(now()->floatDiffInDays(Carbon::parse($this->trial_ends_at), false));
    }
}
