<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OltZone extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $fillable = [
        'name',
        'last_synced_at'
    ];

    protected $appends = ['last_synced_at_humans'];

    protected $casts = ['last_synced_at' => 'datetime'];

    public function splitters()
    {
        return $this->hasMany(OltOdb::class, 'zone_id', 'id');
    }

    public function getLastSyncedAtHumansAttribute()
    {
        return $this->last_synced_at->diffForHumans();
    }
}
