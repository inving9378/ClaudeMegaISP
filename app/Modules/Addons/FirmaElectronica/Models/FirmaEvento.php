<?php

namespace App\Modules\Addons\FirmaElectronica\Models;

use Illuminate\Database\Eloquent\Model;

class FirmaEvento extends Model
{
    public $timestamps = false;
    protected $table = 'firma_eventos';
    protected $guarded = ['id'];
    protected $casts = ['payload' => 'array', 'recibido_at' => 'datetime'];
}
