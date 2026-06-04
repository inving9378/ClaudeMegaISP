<?php

namespace App\Modules\Addons\Talento\Models;

use Illuminate\Database\Eloquent\Model;

class TalentoProjectDeviation extends Model
{
    protected $table = 'talento_project_deviations';

    public $timestamps = false;

    protected $fillable = [
        'project_id', 'colaborador_id',
        'detected_lat', 'detected_lng',
        'deviation_m', 'sustained_minutes',
        'detected_at', 'supervisor_notified', 'created_at',
    ];

    protected $casts = [
        'supervisor_notified' => 'boolean',
        'detected_at'         => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(TalentoProject::class, 'project_id');
    }

    public function colaborador()
    {
        return $this->belongsTo(TalentoColaborador::class, 'colaborador_id');
    }
}
