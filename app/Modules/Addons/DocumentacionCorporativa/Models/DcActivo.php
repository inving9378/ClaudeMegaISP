<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Activo físico o de infraestructura — apartado V: torres, antenas, postería,
 * fibra, redes troncales, equipo de transmisión, vehículos, cómputo,
 * herramientas, centros de distribución y bodegas.
 *
 * `lat`/`lng` alimentan el mapa Leaflet del apartado; las categorías sin
 * ubicación física real (cómputo, herramienta) simplemente los dejan null.
 */
class DcActivo extends Model
{
    use SoftDeletes;

    protected $table = 'dc_activos';

    protected $fillable = [
        'empresa_id', 'categoria', 'nombre', 'descripcion', 'identificador',
        'ubicacion', 'lat', 'lng', 'fecha_adquisicion', 'valor_adquisicion',
        'estado', 'responsable_user_id', 'notas',
    ];

    protected $casts = [
        'lat'                => 'decimal:7',
        'lng'                => 'decimal:7',
        'fecha_adquisicion'  => 'date',
        'valor_adquisicion'  => 'decimal:2',
    ];

    public const CATEGORIAS = [
        'torre', 'antena', 'posteria', 'fibra', 'red_troncal',
        'equipo_transmision', 'vehiculo', 'computo', 'herramienta',
        'centro_distribucion', 'bodega', 'otro',
    ];

    /** Categorías que la solicitud pide ver en el mapa (config.mapa=true del catálogo). */
    public const CATEGORIAS_CON_MAPA = [
        'torre', 'posteria', 'fibra', 'red_troncal', 'centro_distribucion', 'bodega',
    ];

    public const ESTADOS = ['activo', 'baja', 'mantenimiento'];

    public function empresa()
    {
        return $this->belongsTo(DcEmpresa::class, 'empresa_id');
    }

    public function responsable()
    {
        return $this->belongsTo(User::class, 'responsable_user_id');
    }

    public function scopeDeEmpresa($query, int $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }

    public function scopeConUbicacion($query)
    {
        return $query->whereNotNull('lat')->whereNotNull('lng');
    }
}
