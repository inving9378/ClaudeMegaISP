<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Plantilla de orden de Talento (item #1200): campos que se piden al crear o al cerrar,
 * checklist de materiales, prioridad y tiempo estimado. Reemplaza a template_tasks de
 * Scheduling. El esquema de `fields` lo valida Support\EsquemaCamposOrden.
 *
 * Nunca se borra: se desactiva con active=0 (las órdenes guardan su propia copia del esquema).
 */
class TalentoWorkOrderTemplate extends BaseModel
{
    use SoftDeletes;

    protected $table = 'talento_work_order_templates';

    public const PRIORIDADES = ['Alta', 'Media', 'Baja'];

    protected $fillable = [
        'type_id', 'name', 'title', 'description', 'priority', 'estimated_minutes',
        'fields', 'checklist', 'active', 'sort_order', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'fields'            => 'array',
        'checklist'         => 'array',
        'active'            => 'boolean',
        'sort_order'        => 'integer',
        'estimated_minutes' => 'integer',
        'type_id'           => 'integer',
    ];

    public function type()
    {
        return $this->belongsTo(TalentoWorkOrderType::class, 'type_id');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
