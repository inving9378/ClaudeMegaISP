<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OltInterruptionPon extends Model
{
    use HasFactory;

    protected $fillable = [
        'board',
        'port',
        'cause',
        'power_count',
        'total_onus',
        'los_count',
        'latest_status_change',
        'pon_description',
        'last_synced_at',
        'olt_id'
    ];

    protected $appends = ['last_synced_at_humans', 'latest_status_change_humans', 'olt_str'];

    protected $casts = [
        'last_synced_at' => 'datetime',
        'latest_status_change' => 'datetime'
    ];

    public function olt()
    {
        return $this->belongsTo(Olt::class);
    }

    public function getLastSyncedAtHumansAttribute()
    {

        return $this->last_synced_at->diffForHumans();
    }

    public function getLatestStatusChangeHumansAttribute()
    {

        return $this->latest_status_change ? $this->latest_status_change->diffForHumans() : 'N/A';
    }

    public function getOltStrAttribute()
    {

        return $this->olt()->first()->name;
    }
}
