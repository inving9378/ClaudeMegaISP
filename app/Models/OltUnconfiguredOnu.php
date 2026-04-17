<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OltUnconfiguredOnu extends Model
{
    use HasFactory;

    protected $fillable = [
        'board',
        'port',
        'pon_type',
        'sn',
        'onu_type_name',
        'last_synced_at',
        'olt_id',
        'pon_description'
    ];

    protected $appends = ['last_synced_at_humans', 'olt_str'];

    protected $casts = ['last_synced_at' => 'datetime'];

    public function olt()
    {
        return $this->belongsTo(Olt::class);
    }

    public function getLastSyncedAtHumansAttribute()
    {
        return $this->last_synced_at->diffForHumans();
    }

    public function getOltStrAttribute()
    {
        return $this->olt()->first()->name;
    }
}
