<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\BaseModel;
use App\Models\User;

class TalentoAttendance extends BaseModel
{
    protected $table = 'talento_attendances';

    protected $fillable = [
        'colaborador_id', 'check_in_at', 'check_in_lat', 'check_in_lng',
        'check_in_site_id', 'check_in_work_order_id', 'check_in_project_id',
        'check_in_within_geofence', 'check_in_flagged', 'check_in_flag_reason',
        'expected_end_at', 'check_out_at', 'check_out_lat', 'check_out_lng',
        'day_type', 'status', 'notes',
    ];

    protected $casts = [
        'check_in_at'              => 'datetime',
        'check_in_lat'             => 'decimal:7',
        'check_in_lng'             => 'decimal:7',
        'check_in_within_geofence' => 'boolean',
        'check_in_flagged'         => 'boolean',
        'expected_end_at'          => 'datetime',
        'check_out_at'             => 'datetime',
        'check_out_lat'            => 'decimal:7',
        'check_out_lng'            => 'decimal:7',
    ];

    public function colaborador()
    {
        return $this->belongsTo(TalentoColaborador::class);
    }

    public function site()
    {
        return $this->belongsTo(TalentoWorkSite::class, 'check_in_site_id');
    }

    public function workOrder()
    {
        return $this->belongsTo(TalentoWorkOrder::class, 'check_in_work_order_id');
    }

    public function pings()
    {
        return $this->hasMany(TalentoLocationPing::class, 'attendance_id')->orderBy('recorded_at');
    }

    public function extensions()
    {
        return $this->hasMany(TalentoShiftExtension::class, 'attendance_id')->orderBy('created_at');
    }

    public function isOpen(): bool
    {
        // Un turno flagged (fuera de cerca / GPS impreciso) sigue estando ABIERTO:
        // se registró la entrada pero no la salida. Debe poder cerrarse.
        return in_array($this->status, ['open', 'flagged'], true) && $this->check_out_at === null;
    }

    public function scopeOpen($query)
    {
        // Abierto = entrada sin salida. Incluye 'flagged' (AttendanceService::checkIn
        // marca status='flagged' cuando cae fuera de cerca o con GPS impreciso, sin
        // bloquear); de lo contrario checkOut no podría cerrarlo. Sólo lo usa
        // AttendanceService (checkIn/checkOut/storePing).
        return $query->whereIn('status', ['open', 'flagged'])->whereNull('check_out_at');
    }

    public function scopeForPeriod($query, $start, $end)
    {
        return $query->whereBetween('check_in_at', [$start, $end]);
    }
}
