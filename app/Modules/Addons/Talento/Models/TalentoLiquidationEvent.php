<?php

namespace App\Modules\Addons\Talento\Models;

use Illuminate\Database\Eloquent\Model;

/** UNI-T J4: evento append-only de una liquidación (sin updated_at). */
class TalentoLiquidationEvent extends Model
{
    public $timestamps = false;

    protected $table = 'talento_liquidation_events';

    protected $guarded = [];

    protected $casts = ['created_at' => 'datetime'];
}
