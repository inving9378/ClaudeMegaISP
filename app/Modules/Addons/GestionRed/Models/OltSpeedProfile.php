<?php

namespace App\Modules\Addons\GestionRed\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OltSpeedProfile extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $fillable = [
        'name',
        'speed',
        'direction',
        'type',
    ];
}
