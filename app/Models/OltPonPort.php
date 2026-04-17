<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OltPonPort extends Model
{
    use HasFactory;

    protected $fillable = [
        'board',
        'pon_port',
        'pon_type',
        'admin_status',
        'operational_status',
        'onus_count',
        'online_onus_count',
        'average_signal',
        'min_range',
        'max_range',
        'tx_power',
        'description',
        'last_synced_at',
        'olt_id'
    ];

    protected $appends = ['operational_status_cls', 'last_synced_at_humans'];

    protected $casts = ['last_synced_at' => 'datetime'];

    public function olt()
    {
        return $this->belongsTo(Olt::class);
    }

    public function getOperationalStatusClsAttribute()
    {
        return match (strtolower($this->operational_status)) {
            'up', 'up / autofind', 'active', 'enabled' => 'positive',
            'partially up' => 'warning',
            'down', 'down / autofind', 'disabled', 'failed' => 'danger',
            default => 'secondary',
        };
    }

    public function getLastSyncedAtHumansAttribute()
    {
        return $this->last_synced_at->diffForHumans();
    }
}
