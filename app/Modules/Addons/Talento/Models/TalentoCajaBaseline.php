<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\BaseModel;

class TalentoCajaBaseline extends BaseModel
{
    protected $table = 'talento_caja_baselines';

    protected $fillable = ['caja_ref', 'olt_onu_id', 'baseline_power_dbm', 'registered_by', 'registered_at', 'notes'];

    protected $casts = [
        'baseline_power_dbm' => 'decimal:2',
        'registered_at'      => 'datetime',
    ];

    /** Latest baseline for a given caja_ref */
    public static function latestFor(string $cajaRef): ?self
    {
        return static::where('caja_ref', $cajaRef)->latest('registered_at')->first();
    }
}
