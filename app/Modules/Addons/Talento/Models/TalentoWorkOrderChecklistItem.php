<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\BaseModel;

/**
 * Renglón del checklist de materiales de una orden (item #1200), copiado de la plantilla al
 * crearla. Marcarlo (checked/checked_at/checked_by) es la Fase D (#1202).
 */
class TalentoWorkOrderChecklistItem extends BaseModel
{
    protected $table = 'talento_work_order_checklist_items';

    protected $fillable = ['tarea_id', 'label', 'cantidad', 'unidad', 'sort_order', 'checked', 'checked_at', 'checked_by'];

    protected $casts = [
        'tarea_id'   => 'integer',
        'cantidad'   => 'float',
        'sort_order' => 'integer',
        'checked'    => 'boolean',
        'checked_at' => 'datetime',
    ];
}
