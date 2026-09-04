<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Poder otorgado y su vigencia — apartado I.
 *
 * El estado de vigencia se DERIVA de `vigencia_fin`, mismo criterio que
 * `DcDocumento::getEstadoAttribute()` (sin columna que lo respalde: una copia
 * persistida envejecería sola a medianoche). `revocado`, en cambio, SÍ se
 * persiste — es un hecho administrativo que nadie puede derivar de una fecha.
 */
class DcPoder extends Model
{
    use SoftDeletes;

    protected $table = 'dc_poderes';

    protected $fillable = [
        'empresa_id', 'apoderado', 'tipo_poder', 'alcance', 'fecha_otorgamiento',
        'vigencia_fin', 'revocado', 'documento_id',
    ];

    protected $casts = [
        'fecha_otorgamiento' => 'date',
        'vigencia_fin'       => 'date',
        'revocado'           => 'boolean',
    ];

    protected $appends = ['estado'];

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
        if ($this->revocado) {
            return self::ESTADO_VENCIDO;
        }

        if ($this->vigencia_fin === null) {
            return self::ESTADO_VIGENTE;
        }

        $hoy = now()->startOfDay();

        if ($this->vigencia_fin->lt($hoy)) {
            return self::ESTADO_VENCIDO;
        }

        if ($this->vigencia_fin->lte($hoy->copy()->addDays(self::DIAS_AVISO))) {
            return self::ESTADO_POR_VENCER;
        }

        return self::ESTADO_VIGENTE;
    }
}
