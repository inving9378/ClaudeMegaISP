<?php

namespace App\Models\Referrals;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferralCommissionTier extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\ReferralCommissionTierFactory::new();
    }

    protected $table = 'referral_commission_tiers';

    protected $fillable = [
        'tier_name',
        'speed_min_mbps',
        'speed_max_mbps',
        'level_1_pct',
        'level_2_pct',
        'level_3_pct',
        'level_4_pct',
        'level_5_pct',
        'active',
    ];

    protected $casts = [
        'level_1_pct' => 'decimal:2',
        'level_2_pct' => 'decimal:2',
        'level_3_pct' => 'decimal:2',
        'level_4_pct' => 'decimal:2',
        'level_5_pct' => 'decimal:2',
        'active'      => 'boolean',
    ];

    /**
     * Resuelve el tier correcto a partir de velocidad en Mbps.
     * La velocidad viene de Internet.download_speed / 1024 (Kbps → Mbps).
     */
    public static function resolveForSpeedMbps(float $mbps): ?self
    {
        return static::where('active', true)
            ->where('speed_min_mbps', '<=', $mbps)
            ->where('speed_max_mbps', '>=', $mbps)
            ->first();
    }

    public function getRateForLevel(int $level): float
    {
        $column = "level_{$level}_pct";
        return (float) ($this->$column ?? 0);
    }
}
