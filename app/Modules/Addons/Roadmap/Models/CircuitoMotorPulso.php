<?php

namespace App\Modules\Addons\Roadmap\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un pulso (corrida ok/fallo) de un motor programado del circuito (#875). Model plano (no
 * BaseModel: lo escribe el listener de `CommandFinished`, sin sesión de usuario). Sin timestamps
 * propios de Eloquent — el momento lo llevan `inicio_at`/`fin_at`.
 */
class CircuitoMotorPulso extends Model
{
    public $timestamps = false;

    protected $table = 'circuito_motor_pulsos';

    protected $fillable = ['motor', 'inicio_at', 'fin_at', 'ok', 'mensaje', 'duracion_ms'];

    protected $casts = [
        'inicio_at' => 'datetime',
        'fin_at'    => 'datetime',
        'ok'        => 'boolean',
    ];
}
