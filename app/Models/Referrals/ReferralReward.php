<?php

namespace App\Models\Referrals;

use App\Models\Client;
use App\Models\ClientInvoice;
use App\Traits\BelongsToClientTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralReward extends Model
{
    use HasFactory;
    use BelongsToClientTenant;

    /** Aislamiento por cliente fail-closed. INERTE: solo actúa si una query llama ->forClient(). */
    protected string $tenantColumn = 'embajador_id';
    protected bool $allowNullTenant = false;

    protected static function newFactory()
    {
        return \Database\Factories\ReferralRewardFactory::new();
    }

    protected $table = 'referral_rewards';

    protected $fillable = [
        'embajador_id',
        'referral_id',
        'type',
        'plan_value_snapshot',
        'status',
        'available_at',
        'applied_at',
        'applied_invoice_id',
        'expires_at',
    ];

    protected $casts = [
        'plan_value_snapshot' => 'decimal:2',
        'available_at'        => 'datetime',
        'applied_at'          => 'datetime',
        'expires_at'          => 'datetime',
    ];

    public function embajador(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'embajador_id');
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class, 'referral_id');
    }

    public function appliedInvoice(): BelongsTo
    {
        return $this->belongsTo(ClientInvoice::class, 'applied_invoice_id');
    }

    public function isAvailable(): bool
    {
        return $this->status === 'available'
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
