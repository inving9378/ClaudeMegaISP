<?php

namespace App\Modules\Addons\MapaRed\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * MR-23 fase 4a (item #9990518) — enlace trazado entre dos nodos del mapa (ambos siempre
 * `MapaRedLayer`, el modelo unificado de "elementos del mapa").
 */
class MapaRedEnlace extends Model
{
    public const TIPOS = ['fibra', 'inalambrico', 'cobre'];
    public const ESTADOS = ['activo', 'inactivo'];

    protected $table = 'mapared_enlaces';

    protected $fillable = [
        'nodo_origen_id',
        'nodo_destino_id',
        'tipo',
        'estado',
    ];

    public function nodoOrigen()
    {
        return $this->belongsTo(MapaRedLayer::class, 'nodo_origen_id');
    }

    public function nodoDestino()
    {
        return $this->belongsTo(MapaRedLayer::class, 'nodo_destino_id');
    }
}
