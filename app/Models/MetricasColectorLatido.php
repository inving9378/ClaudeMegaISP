<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Una corrida de metricas:colectar (item #988): si no hay latido en un minuto, no hubo lectura. */
class MetricasColectorLatido extends Model
{
    protected $table = 'metricas_colector_latidos';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'ts'      => 'datetime',
        'detalle' => 'array',
    ];
}
