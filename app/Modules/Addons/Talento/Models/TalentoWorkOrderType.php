<?php

namespace App\Modules\Addons\Talento\Models;

use App\Models\BaseModel;

class TalentoWorkOrderType extends BaseModel
{
    protected $table = 'talento_work_order_types';

    protected $fillable = ['name', 'category', 'area', 'project_id', 'color', 'points', 'is_billable', 'requires_validation', 'inicia_garantia', 'active'];

    /** Área del tipo (item #1200): de cliente (instalación, garantía…) o de planta externa. */
    public const AREA_CLIENTE = 'cliente';
    public const AREA_PLANTA_EXTERNA = 'planta_externa';
    public const AREAS = [self::AREA_CLIENTE, self::AREA_PLANTA_EXTERNA];

    /**
     * Rojo reservado para las órdenes atrasadas (item #1158): ningún tipo lo usa, así una orden que
     * no se hizo en su día se distingue de cualquier tipo de trabajo.
     */
    public const COLOR_ATRASADA = '#D50000';

    /** Colores de respaldo para tipos sin color elegido (se reparten por id). */
    private const COLORES_RESPALDO = ['#3949AB', '#039BE5', '#7CB342', '#F6BF26', '#E67C73', '#795548'];

    /** Color con el que se pinta el tipo: el elegido o uno de respaldo estable por id. */
    public function colorEfectivo(): string
    {
        if ($this->color) {
            return $this->color;
        }

        return self::COLORES_RESPALDO[((int) $this->id) % count(self::COLORES_RESPALDO)];
    }

    protected $casts = [
        'is_billable'          => 'boolean',
        'requires_validation'  => 'boolean',
        'active'               => 'boolean',
        'inicia_garantia'      => 'boolean',
        'points'               => 'integer',
        'project_id'           => 'integer',
    ];

    public function esPlantaExterna(): bool
    {
        return $this->area === self::AREA_PLANTA_EXTERNA;
    }

    /** Plantillas de orden del tipo (item #1200). */
    public function templates()
    {
        return $this->hasMany(TalentoWorkOrderTemplate::class, 'type_id');
    }

    public function workOrders()
    {
        return $this->hasMany(TalentoWorkOrder::class, 'type_id');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
