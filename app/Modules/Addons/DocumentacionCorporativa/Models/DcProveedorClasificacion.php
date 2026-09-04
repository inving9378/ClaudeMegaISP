<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Models;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Clasificación de un proveedor para el Apartado X (telecomunicaciones,
 * tecnologia, contratistas, programadores, desarrolladores, capacitadores,
 * asesores, contadores, despachos, estrategicos). Un proveedor puede tener
 * varias filas (varias clasificaciones a la vez).
 */
class DcProveedorClasificacion extends Model
{
    use SoftDeletes;

    protected $table = 'dc_proveedor_clasificaciones';

    protected $fillable = [
        'supplier_id', 'clasificacion',
    ];

    public const CLASIFICACIONES = [
        'telecomunicaciones', 'tecnologia', 'contratistas', 'programadores',
        'desarrolladores', 'capacitadores', 'asesores', 'contadores',
        'despachos', 'estrategicos',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
