<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Ping a una IP de un dedicado en un minuto, hecho desde su MikroTik (item #988). Model plano. */
class ClienteMetricaPing extends Model
{
    protected $table = 'cliente_metricas_ping';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'ts'         => 'datetime',
        'lat_min_ms' => 'float',
        'lat_avg_ms' => 'float',
        'lat_max_ms' => 'float',
    ];

    public const RESPONDE = 'responde';
    public const SIN_RESPUESTA = 'sin_respuesta';
    public const OMITIDO = 'omitido';
    public const ERROR = 'error';
}
