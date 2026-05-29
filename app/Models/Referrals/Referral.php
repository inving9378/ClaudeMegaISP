<?php

namespace App\Models\Referrals;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Referral extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\ReferralFactory::new();
    }

    protected $table = 'referrals';

    protected $fillable = [
        'embajador_id',
        'referred_client_id',
        'prospect_id',
        'chain_path',
        'chain_depth',
        'referred_threshold_amount_paid',
        'referred_threshold_covered_at',
        'commission_window_start',
        'commissions_paid_count',
        'status',
    ];

    protected $casts = [
        'referred_threshold_covered_at' => 'datetime',
        'commission_window_start'       => 'datetime',
        'referred_threshold_amount_paid' => 'decimal:2',
    ];

    public function embajador(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'embajador_id');
    }

    public function referredClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'referred_client_id');
    }

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(ReferralProspect::class, 'prospect_id');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(ReferralCommission::class, 'referral_id');
    }

    public function reward(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ReferralReward::class, 'referral_id');
    }

    /**
     * Devuelve los IDs de beneficiarios upstream en orden de nivel (nivel 1 primero).
     *
     * chain_path = '/1001/2002/3003/' donde 3003 es el embajador directo (nivel 1).
     * array_reverse invierte el orden para que el primer elemento sea el nivel 1.
     */
    public function getUpstreamChain(int $maxLevels = 5): array
    {
        $parts    = array_filter(explode('/', $this->chain_path));
        $reversed = array_reverse(array_values($parts));
        return array_slice($reversed, 0, $maxLevels);
    }

    public function isWithinCommissionWindow(): bool
    {
        return $this->status === 'active'
            && $this->commissions_paid_count < 12;
    }
}
