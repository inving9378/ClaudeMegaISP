<?php

namespace App\Modules\Addons\Roadmap\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Una ejecución (vuelta) del ejecutor on-box del Circuito. Model plano (no BaseModel:
 * lo escribe el cron/comando, sin sesión de usuario).
 */
class CircuitoEjecucion extends Model
{
    protected $table = 'circuito_ejecuciones';

    protected $fillable = [
        'started_at', 'finished_at', 'duracion_seg', 'modo', 'pausado', 'rc',
        'items_tocados', 'n_propuestas', 'n_decisiones', 'ejecuto', 'resumen', 'log_path',
    ];

    protected $casts = [
        'started_at'   => 'datetime',
        'finished_at'  => 'datetime',
        'items_tocados' => 'array',
        'pausado'      => 'boolean',
        'ejecuto'      => 'boolean',
    ];
}
