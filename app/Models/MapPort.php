<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MapPort extends Model
{
    use HasFactory;

    protected $table = 'map_ports';

    protected $fillable = [
        'name',
        'type',
        'splitter_id',
        'connected',
        'position_x',
        'position_y',
        'orientation',
        'transfer',
        'transfer_type',
        'note'
    ];

    protected $appends = ['selected'];

    protected $casts = [
        'connected' => 'boolean'
    ];

    protected $with = ['client'];

    public function device()
    {
        return $this->morphTo();
    }

    public function client()
    {
        return $this->belongsTo(ClientMainInformation::class, 'client_id');
    }

    public function connection_from()
    {
        return $this->hasOne(MapConnection::class, 'from_id');
    }

    public function connection_to()
    {
        return $this->hasOne(MapConnection::class, 'to_id');
    }

    public function getSelectedAttribute()
    {
        return false;
    }

    public function getNameAttribute($val)
    {
        if (isset($val)) {
            return $val;
        }
        if ($this->client) {
            return $this->client->client_name_with_fathers_names;
        }
        return null;
    }
}
