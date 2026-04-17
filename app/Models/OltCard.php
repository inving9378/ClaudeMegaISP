<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OltCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'real_type',
        'ports',
        'software_version',
        'slot',
        'role',
        'status',
        'info_updated',
        'olt_id',
        'last_synced_at'
    ];

    protected $appends = ['last_synced_at_humans', 'label'];

    protected $casts = ['last_synced_at' => 'datetime'];

    public function olt()
    {
        return $this->belongsTo(Olt::class);
    }

    public function getLastSyncedAtHumansAttribute()
    {
        return $this->last_synced_at->diffForHumans();
    }

    public function getLabelAttribute()
    {
        return $this->slot;
    }
}
