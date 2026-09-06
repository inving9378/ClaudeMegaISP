<?php

namespace App\Modules\Addons\MapaRed\Models;

use App\Models\BaseModel;
use App\Modules\Addons\MapaRed\Services\CableStructureService;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * MR-09 (item #945): el cable deja de ser una línea dibujada y pasa a tener estructura interna
 * (buffers e hilos) instanciada automáticamente al guardar.
 *
 * `tipo_cable_id` es referencia BLANDA (sin FK real) a `mapared_tipo_cable`, catálogo que
 * construye MR-08 (#944) en paralelo y todavía no existe. Mientras tanto `numero_hilos` y
 * `hilos_por_buffer` viajan como snapshot directo en el cable.
 */
class MapaRedCable extends BaseModel
{
    protected $table = 'mapared_cables';

    protected $fillable = [
        'nombre',
        'codigo_tipo',
        'tipo_cable_id',
        'numero_hilos',
        'hilos_por_buffer',
        'geom_json',
        'longitud_metros',
        'holgura_metros',
        'estado',
        'proyecto_id',
        'zona',
        'lat',
        'lng',
        'bbox_min_lat',
        'bbox_max_lat',
        'bbox_min_lng',
        'bbox_max_lng',
        'empresa_id',
    ];

    protected $casts = [
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
        'longitud_metros' => 'decimal:2',
        'holgura_metros' => 'decimal:2',
    ];

    public function hilos(): HasMany
    {
        return $this->hasMany(MapaRedHilo::class, 'cable_id');
    }

    protected static function booted(): void
    {
        static::saving(function (self $cable) {
            if ($cable->isDirty('geom_json')) {
                $cable->longitud_metros = CableStructureService::calcularLongitudMetros($cable->geom_json);
            }
        });

        static::saved(function (self $cable) {
            CableStructureService::sincronizarHilos($cable);
        });
    }
}
