<?php

namespace App\Modules\Addons\MapaRed\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * MR-26 Fase 3 (item roadmap #9990524) — sector inalámbrico (AP/torre sectorizada):
 * azimut/apertura/alcance/altura. Entidad plana, sin FK a otras tablas mapared_* (mismo
 * estilo que MapaRedEnlaceServicio para datos que no necesitan normalizar contra el resto
 * de la planta). D29: sin simulador de propagación RF — solo geometría de referencia para
 * que el frontend (Fase 4) dibuje el cono, sin cálculo real de cobertura.
 */
class MapaRedSectorInalambrico extends Model
{
    protected $table = 'mapared_sectores_inalambricos';

    protected $fillable = [
        'nombre',
        'lat',
        'lng',
        'azimut_grados',
        'apertura_grados',
        'alcance_metros',
        'altura_metros',
        'activo',
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'azimut_grados' => 'float',
        'apertura_grados' => 'float',
        'alcance_metros' => 'integer',
        'altura_metros' => 'float',
        'activo' => 'boolean',
    ];

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Feature GeoJSON Point con properties completas — consumida por el endpoint
     * `GET /mapa-red/api/sectores` y por el importador (respuesta de `confirmar()`).
     */
    public function toGeoJsonFeature(): array
    {
        return [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [$this->lng, $this->lat],
            ],
            'properties' => [
                'id' => $this->id,
                'nombre' => $this->nombre,
                'azimut_grados' => $this->azimut_grados,
                'apertura_grados' => $this->apertura_grados,
                'alcance_metros' => $this->alcance_metros,
                'altura_metros' => $this->altura_metros,
                'activo' => $this->activo,
            ],
        ];
    }
}
