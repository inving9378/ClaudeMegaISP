<?php

namespace App\Models\Referrals;

use Illuminate\Database\Eloquent\Model;

class ReferralSetting extends Model
{
    protected $table = 'referral_settings';

    protected $fillable = [
        'program_active',
        'threshold_amount',
        'duration_months',
        'max_levels',
        'program_name',
        'welcome_message',
        'share_template_default',
        'terms_conditions',
    ];

    protected $casts = [
        'program_active'   => 'boolean',
        'threshold_amount' => 'decimal:2',
        'duration_months'  => 'integer',
        'max_levels'       => 'integer',
    ];

    public static function current(): self
    {
        return static::firstOrCreate([], [
            'program_active'  => true,
            'threshold_amount' => 1500.00,
            'duration_months' => 12,
            'max_levels'      => 5,
            'program_name'    => 'Embajadores Meganet',
        ]);
    }
}
