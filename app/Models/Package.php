<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'url',
        'type',
    ];

    const DEFAULT_PACKAGE_LIST = [1, 2, 3, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 4, 5, 21, 22, 23];
    const PACKAGE_MAP_NAME = 'google_map';
}
