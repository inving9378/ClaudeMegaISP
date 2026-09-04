<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Contrato con cliente, proveedor, convenio comercial, arrendamiento,
 * servicios, mantenimiento, suministro o interconexión — apartado VI.
 * `tipo` distingue la relación comercial (ver filtros del catálogo).
 *
 * El estado de vigencia se DERIVA de `fecha_fin`, mismo criterio que
 * `DcDocumento::getEstadoAttribute()`.
 */
class DcContrato extends Model
{
    use SoftDeletes;

    protected $table = 'dc_contratos';

    protected $fillable = [
        'empresa_id', 'tipo', 'contraparte', 'objeto', 'fecha_inicio',
        'fecha_fin', 'monto', 'documento_id',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin'    => 'date',
        'monto'        => 'decimal:2',
    ];

    protected $appends = ['estado'];

    public const TIPOS = [
        'cliente', 'proveedor', 'convenio_comercial', 'arrendamiento',
        'servicios', 'mantenimiento', 'suministro', 'interconexion',
    ];

    public const ESTADO_VIGENTE    = 'vigente';
    public const ESTADO_POR_VENCER = 'por_vencer';
    public const ESTADO_VENCIDO    = 'vencido';

    public const DIAS_AVISO = 30;

    public function empresa()
    {
        return $this->belongsTo(DcEmpresa::class, 'empresa_id');
    }

    public function documento()
    {
        return $this->belongsTo(DcDocumento::class, 'documento_id');
    }

    public function scopeDeEmpresa($query, int $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }

    public function getEstadoAttribute(): string
    {
        if ($this->fecha_fin === null) {
            return self::ESTADO_VIGENTE;
        }

        $hoy = now()->startOfDay();

        if ($this->fecha_fin->lt($hoy)) {
            return self::ESTADO_VENCIDO;
        }

        if ($this->fecha_fin->lte($hoy->copy()->addDays(self::DIAS_AVISO))) {
            return self::ESTADO_POR_VENCER;
        }

        return self::ESTADO_VIGENTE;
    }
}
