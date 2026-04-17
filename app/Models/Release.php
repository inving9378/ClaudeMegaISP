<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Release extends Model
{
    use HasFactory;

    protected $fillable = [
        'version',
        'title',
        'summary',
        'description',
        'release_date',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'release_date' => 'date',
    ];
}
