<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un concepto del expediente: la unidad que se resuelve, se documenta o se
 * declara pendiente. Son 139 sembrados + los que agregue el usuario.
 *
 * Los sembrados NO se borran: se desactivan con `activo = false`.
 */
class DcConcepto extends Model
{
    use SoftDeletes;

    protected $table = 'dc_conceptos';

    protected $fillable = [
        'empresa_id', 'apartado_id', 'nombre', 'slug', 'tipo_resolvedor', 'config',
        'plantilla_id', 'obligatorio', 'rol_responsable', 'periodicidad_revision',
        'base_legal', 'confidencialidad', 'orden', 'activo',
    ];

    protected $casts = [
        'config'      => 'array',
        'obligatorio' => 'boolean',
        'activo'      => 'boolean',
    ];

    public const RESOLVEDORES = [
        'sistema', 'documento', 'plantilla', 'grafica', 'inventario', 'pendiente',
    ];

    public const CONFIDENCIALIDADES = ['interna', 'restringida', 'critica'];

    public function empresa()
    {
        return $this->belongsTo(DcEmpresa::class, 'empresa_id');
    }

    public function apartado()
    {
        return $this->belongsTo(DcApartado::class, 'apartado_id');
    }

    public function documentos()
    {
        return $this->hasMany(DcDocumento::class, 'concepto_id');
    }

    public function pendientes()
    {
        return $this->hasMany(DcPendiente::class, 'concepto_id');
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeObligatorios($query)
    {
        return $query->where('obligatorio', true);
    }

    public function scopeDeEmpresa($query, int $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }
}
