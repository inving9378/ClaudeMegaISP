<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Una obligación de pago (derecho, refrendo, contraprestación) de una
 * concesión. `fecha_pago` nulo = todavía pendiente.
 */
class DcConcesionPago extends Model
{
    use SoftDeletes;

    protected $table = 'dc_concesion_pagos';

    protected $fillable = [
        'empresa_id', 'concesion_id', 'concepto', 'periodo', 'monto',
        'fecha_vencimiento', 'fecha_pago', 'comprobante_documento_id',
    ];

    protected $casts = [
        'monto'             => 'decimal:2',
        'fecha_vencimiento' => 'date',
        'fecha_pago'        => 'date',
    ];

    protected $appends = ['pagado'];

    public function concesion()
    {
        return $this->belongsTo(DcConcesion::class, 'concesion_id');
    }

    public function comprobante()
    {
        return $this->belongsTo(DcDocumento::class, 'comprobante_documento_id');
    }

    public function scopeDeEmpresa($query, int $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }

    public function scopePendientes($query)
    {
        return $query->whereNull('fecha_pago');
    }

    public function getPagadoAttribute(): bool
    {
        return $this->fecha_pago !== null;
    }
}
