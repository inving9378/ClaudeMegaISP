<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\BaseModel;

/**
 * Datos capturados de una orden con plantilla (item #1200). `schema_snapshot` es la copia de
 * los campos de la plantilla al crear la orden; `values` son los valores ya normalizados.
 * Una fila por tarea (tasks.id).
 */
class TalentoWorkOrderData extends BaseModel
{
    protected $table = 'talento_work_order_data';

    protected $fillable = ['tarea_id', 'template_id', 'schema_snapshot', 'values', 'created_by', 'updated_by'];

    protected $casts = [
        'schema_snapshot' => 'array',
        'values'          => 'array',
        'tarea_id'        => 'integer',
        'template_id'     => 'integer',
    ];

    public function template()
    {
        return $this->belongsTo(TalentoWorkOrderTemplate::class, 'template_id')->withTrashed();
    }
}
