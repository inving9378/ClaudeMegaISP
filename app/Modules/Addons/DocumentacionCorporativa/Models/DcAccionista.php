<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Fila del libro de registro de acciones — apartado I.
 */
class DcAccionista extends Model
{
    use SoftDeletes;

    protected $table = 'dc_accionistas';

    protected $fillable = [
        'empresa_id', 'nombre_razon_social', 'porcentaje', 'num_acciones',
        'tipo_serie', 'fecha_alta', 'fecha_baja',
    ];

    protected $casts = [
        'porcentaje'    => 'decimal:2',
        'num_acciones'  => 'integer',
        'fecha_alta'    => 'date',
        'fecha_baja'    => 'date',
    ];

    public function empresa()
    {
        return $this->belongsTo(DcEmpresa::class, 'empresa_id');
    }

    public function scopeDeEmpresa($query, int $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }
}
