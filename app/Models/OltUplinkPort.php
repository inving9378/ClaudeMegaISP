<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OltUplinkPort extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'mode',
        'admin_status',
        'status',
        'vlan_tag',
        'negotiation_auto',
        'mtu',
        'wavelength',
        'temperature',
        'pvid',
        'description',
        'last_synced_at',
        'olt_id'
    ];

    protected $appends = ['last_synced_at_humans'];

    protected $casts = ['last_synced_at' => 'datetime'];

    public function olt()
    {
        return $this->belongsTo(Olt::class);
    }

    public function getLastSyncedAtHumansAttribute()
    {
        return $this->last_synced_at->diffForHumans();
    }
}
