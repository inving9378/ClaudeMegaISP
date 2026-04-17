<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DurationContract extends Model
{
    use HasFactory;

    protected $table = 'duration_contracts';

    protected $fillable = [
        'name',
        'duration',
    ];
}
