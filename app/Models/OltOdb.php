<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OltOdb extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $fillable = [
        'name',
        'latitude',
        'longitude',
        'zone_id',
        'zone_name',
        'last_synced_at',
    ];

    protected $appends = ['last_synced_at_humans', 'label'];

    protected $casts = ['last_synced_at' => 'datetime'];

    public function getLastSyncedAtHumansAttribute()
    {
        return $this->last_synced_at?->diffForHumans() ?? 'N/A';
    }

    public function getLabelAttribute()
    {
        return $this->name;
    }
}
