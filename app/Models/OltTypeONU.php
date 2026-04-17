<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OltTypeONU extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $table = 'olt_type_onus';

    protected $fillable = [
        'name',
        'pon_type',
        'capability',
        'ethernet_ports',
        'wifi_ports',
        'voip_ports',
        'catv',
        'allow_custom_profiles',
        'last_synced_at'
    ];

    protected $appends = ['last_synced_at_humans'];

    protected $casts = [
        'last_synced_at' => 'datetime',
        'allow_custom_profiles' => 'boolean'
    ];

    public function getLastSyncedAtHumansAttribute()
    {
        return $this->last_synced_at?->diffForHumans() ?? 'N/A';
    }
}
