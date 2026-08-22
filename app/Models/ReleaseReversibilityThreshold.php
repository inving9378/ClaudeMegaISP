<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReleaseReversibilityThreshold extends Model
{
    protected $fillable = [
        'criticidad',
        'umbral_filas',
    ];
}
