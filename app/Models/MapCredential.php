<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MapCredential extends Model
{
    use HasFactory;

    protected $table = 'system_map_credentials';

    protected $fillable = [
        'latitude',
        'longitude',
    ];
}
