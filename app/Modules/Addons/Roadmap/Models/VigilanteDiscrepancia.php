<?php

namespace App\Modules\Addons\Roadmap\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Una discrepancia declarado-por-supervisor vs proceso-real-del-SO, correlacionada por PID.
 * Ver el docblock de la migración `2026_08_29_180000_crea_vigilante_discrepancias` para el
 * porqué. Se escribe únicamente desde `CompuertasSondaCommand::medirWorkers()`.
 */
class VigilanteDiscrepancia extends Model
{
    protected $table = 'vigilante_discrepancias';

    public $timestamps = false;

    protected $fillable = [
        'programa',
        'estado_supervisor',
        'estado_real',
        'pids_supervisor',
        'pids_reales',
        'detectado_at',
        'resuelto_at',
    ];

    protected $casts = [
        'detectado_at' => 'datetime',
        'resuelto_at'  => 'datetime',
    ];
}
