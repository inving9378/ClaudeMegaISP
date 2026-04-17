<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MapConnection extends Model
{
    use HasFactory;

    protected $table = 'map_connections';

    protected $fillable = [
        'from_id',
        'to_id',
        'type',
        'color',
        'width',
        'animate',
        'from_type',
        'to_type'
    ];

    protected $with = ['from', 'to'];

    protected static function boot()
    {
        parent::boot();
        static::created(function ($obj) {
            $obj->from()->update(['connected' => true]);
            $obj->to()->update(['connected' => true]);
        });
    }

    public function from()
    {
        return $this->belongsTo(MapPort::class, 'from_id');
    }

    public function to()
    {
        return $this->belongsTo(MapPort::class, 'to_id');
    }
}
