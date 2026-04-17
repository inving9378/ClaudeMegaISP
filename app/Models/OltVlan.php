<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OltVlan extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $fillable = [
        'vlan',
        'scope',
        'description',
        'last_synced_at',
        'olt_id'
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
        return $this->description ? sprintf('%s - %s', $this->vlan, $this->description) : $this->vlan;
    }
}
