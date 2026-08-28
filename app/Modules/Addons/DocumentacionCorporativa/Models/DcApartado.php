<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Uno de los 14 apartados (I a XIV) de la solicitud de información corporativa.
 */
class DcApartado extends Model
{
    use SoftDeletes;

    protected $table = 'dc_apartados';

    protected $fillable = [
        'empresa_id', 'clave', 'nombre', 'descripcion', 'icono', 'orden', 'activo',
    ];

    protected $casts = ['activo' => 'boolean'];

    /** Las 14 claves, en orden. Fuente de los permisos `.apartado.{clave}.view`. */
    public const CLAVES = [
        'I', 'II', 'III', 'IV', 'V', 'VI', 'VII',
        'VIII', 'IX', 'X', 'XI', 'XII', 'XIII', 'XIV',
    ];

    public function empresa()
    {
        return $this->belongsTo(DcEmpresa::class, 'empresa_id');
    }

    public function conceptos()
    {
        return $this->hasMany(DcConcepto::class, 'apartado_id')->orderBy('orden');
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeDeEmpresa($query, int $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }

    /** Permiso que gatea este apartado en concreto. */
    public function permiso(): string
    {
        return 'documentacion-corporativa.apartado.' . mb_strtolower($this->clave) . '.view';
    }
}
