<?php

namespace App\Modules\Addons\MapaRed\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo de tipos de cable de fibra (MR-08 Fase 1, item roadmap #9990503, sub-item de #944).
 *
 * `atenuacion_db_km` es un mapa ventana_óptica => dB/km (D15), mismos valores que
 * `OpticalBudgetService::ATENUACION_DB_KM` (que sigue siendo el catálogo mínimo en código hasta
 * que una fase futura conecte el cálculo a este catálogo en BD).
 */
class MapaRedTipoCable extends Model
{
    protected $table = 'mapared_tipo_cable';

    protected $fillable = [
        'nombre',
        'fabricante',
        'numero_hilos',
        'hilos_por_buffer',
        'esquema_color',
        'atenuacion_db_km',
        'precio',
        'unidad_medida',
    ];

    protected $casts = [
        'numero_hilos' => 'integer',
        'hilos_por_buffer' => 'integer',
        'atenuacion_db_km' => 'array',
        'precio' => 'float',
    ];
}
