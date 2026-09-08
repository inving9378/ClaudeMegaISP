<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Empresa emisora del expediente corporativo.
 *
 * Modelo plano (no BaseModel): estas tablas no llevan `created_by`/`updated_by`,
 * y BaseModel los estampa siempre.
 */
class DcEmpresa extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'dc_empresas';

    protected $fillable = [
        'razon_social', 'nombre_comercial', 'rfc', 'regimen_fiscal',
        'fecha_constitucion', 'domicilio_fiscal', 'activo', 'fecha_inicio_plazo',
    ];

    protected $casts = [
        'fecha_constitucion' => 'date',
        'fecha_inicio_plazo' => 'date',
        'activo'             => 'boolean',
    ];

    public function apartados()
    {
        return $this->hasMany(DcApartado::class, 'empresa_id');
    }

    public function conceptos()
    {
        return $this->hasMany(DcConcepto::class, 'empresa_id');
    }

    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    /** Nombre corto para el selector del header. */
    public function getEtiquetaAttribute(): string
    {
        return $this->nombre_comercial ?: $this->razon_social;
    }
}
