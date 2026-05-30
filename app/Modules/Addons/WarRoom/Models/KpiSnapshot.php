<?php

namespace App\Modules\Addons\WarRoom\Models;

use Illuminate\Database\Eloquent\Model;

class KpiSnapshot extends Model
{
    protected $table = 'warroom_kpi_snapshots';

    protected $guarded = [];

    protected $casts = [
        'kpis'        => 'array',
        'snapshot_at' => 'datetime',
    ];

    public static function forPeriod(string $period): ?self
    {
        return static::where('period', $period)->first();
    }
}
