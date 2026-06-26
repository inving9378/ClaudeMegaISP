<?php

namespace App\Models\Contratable;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Paquete por rango de unidades de un servicio contratable. Precio fijo mensual por
 * el rango completo. `rango_max` NULL = paquete sin tope superior.
 */
class ContratablePackage extends BaseModel
{
    protected $table = 'contratable_packages';

    protected $fillable = [
        'contratable_service_id', 'nombre', 'rango_min', 'rango_max', 'precio', 'orden',
    ];

    protected $casts = [
        'rango_min' => 'integer',
        'rango_max' => 'integer',
        'precio'    => 'decimal:2',
        'orden'     => 'integer',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(ContratableService::class, 'contratable_service_id');
    }
}
