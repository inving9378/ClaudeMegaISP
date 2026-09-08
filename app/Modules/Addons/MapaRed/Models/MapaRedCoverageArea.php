<?php

namespace App\Modules\Addons\MapaRed\Models;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * MR-22 Fase 2c-2 (item roadmap #9990540) — zona de cobertura DECLARADA/editorial: polígono
 * dibujado manualmente por un admin. Distinta de MapaRedSectorInalambrico (MR-26) — esta no
 * se calcula desde la infraestructura, es una afirmación manual del tipo "aquí sí damos
 * servicio". `polygon` sigue el patrón de FleetGeofence::polygon (array de [lat,lng]).
 */
class MapaRedCoverageArea extends BaseModel
{
    use SoftDeletes;

    protected $table = 'mapared_coverage_areas';

    protected $fillable = [
        'nombre',
        'tipo_tecnologia',
        'polygon',
        'activo',
    ];

    protected $casts = [
        'polygon' => 'array',
        'activo'  => 'boolean',
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function (self $area) {
            $area->created_by = auth()->id();
            $area->updated_by = auth()->id();
        });

        static::updating(function (self $area) {
            $area->updated_by = auth()->id();
        });
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /** Feature GeoJSON Polygon — mismo contrato de salida usado por el resto del módulo. */
    public function toGeoJsonFeature(): array
    {
        $ring = array_map(fn ($p) => [(float) ($p[1] ?? 0), (float) ($p[0] ?? 0)], $this->polygon ?? []);
        if (count($ring) > 0 && $ring[0] !== end($ring)) {
            $ring[] = $ring[0];
        }

        return [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Polygon',
                'coordinates' => [$ring],
            ],
            'properties' => [
                'id' => $this->id,
                'nombre' => $this->nombre,
                'tipo_tecnologia' => $this->tipo_tecnologia,
                'activo' => $this->activo,
            ],
        ];
    }
}
