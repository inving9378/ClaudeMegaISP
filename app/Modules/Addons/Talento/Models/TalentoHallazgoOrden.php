<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\BaseModel;

/** Vínculo hallazgo → orden de trabajo (tasks). Lo llena la evaluación del supervisor (#1312). */
class TalentoHallazgoOrden extends BaseModel
{
    protected $table = 'talento_hallazgo_ordenes';

    protected $fillable = ['hallazgo_id', 'tarea_id', 'created_by'];

    protected $casts = [
        'hallazgo_id' => 'integer',
        'tarea_id'    => 'integer',
    ];
}
