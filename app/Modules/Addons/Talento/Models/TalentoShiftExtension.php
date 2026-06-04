<?php

namespace App\Modules\Addons\Talento\Models;

use Illuminate\Database\Eloquent\Model;

class TalentoShiftExtension extends Model
{
    protected $table = 'talento_shift_extensions';
    public $timestamps = false;

    protected $fillable = ['attendance_id', 'minutes', 'reason_category', 'reason_text', 'created_by'];

    protected $casts = [
        'minutes'    => 'integer',
        'created_at' => 'datetime',
    ];

    public function attendance()
    {
        return $this->belongsTo(TalentoAttendance::class);
    }
}
