<?php

namespace App\Modules\Addons\Talento\Models;

use Illuminate\Database\Eloquent\Model;

class TalentoLocationPing extends Model
{
    protected $table = 'talento_location_pings';
    public $timestamps = false;

    protected $fillable = [
        'colaborador_id', 'attendance_id',
        'latitude', 'longitude', 'accuracy_m', 'battery', 'recorded_at',
    ];

    protected $casts = [
        'latitude'    => 'decimal:7',
        'longitude'   => 'decimal:7',
        'accuracy_m'  => 'decimal:2',
        'battery'     => 'integer',
        'recorded_at' => 'datetime',
    ];

    public function attendance()
    {
        return $this->belongsTo(TalentoAttendance::class);
    }
}
