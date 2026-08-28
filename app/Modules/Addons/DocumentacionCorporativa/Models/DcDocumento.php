<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Documento vigente de un concepto. El archivo vive en disco privado bajo un
 * UUID; aquí viven sus metadatos, su hash y su vigencia.
 *
 * El ESTADO de vigencia se deriva, no se persiste: ver `getEstadoAttribute()`.
 */
class DcDocumento extends Model
{
    use SoftDeletes;

    protected $table = 'dc_documentos';

    protected $fillable = [
        'empresa_id', 'concepto_id', 'titulo', 'archivo_uuid', 'archivo_nombre_original',
        'mime', 'bytes', 'hash', 'version_actual', 'vigencia_inicio', 'vigencia_fin',
        'folio', 'contraparte', 'confidencialidad', 'notas', 'subido_por',
    ];

    protected $casts = [
        'vigencia_inicio' => 'date',
        'vigencia_fin'    => 'date',
        'bytes'           => 'integer',
        'version_actual'  => 'integer',
    ];

    protected $appends = ['estado'];

    public const ESTADO_VIGENTE    = 'vigente';
    public const ESTADO_POR_VENCER = 'por_vencer';
    public const ESTADO_VENCIDO    = 'vencido';

    /** Días de antelación con los que un documento entra en `por_vencer`. */
    public const DIAS_AVISO = 30;

    public function empresa()
    {
        return $this->belongsTo(DcEmpresa::class, 'empresa_id');
    }

    public function concepto()
    {
        return $this->belongsTo(DcConcepto::class, 'concepto_id');
    }

    public function versiones()
    {
        return $this->hasMany(DcDocumentoVersion::class, 'documento_id')->orderByDesc('version');
    }

    /**
     * Estado de vigencia. ÚNICA fuente de verdad — no hay columna que lo respalde
     * a propósito: una copia persistida envejecería sola a medianoche.
     */
    public function getEstadoAttribute(): string
    {
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

    /**
     * Documentos que cuentan como "resuelto" para la completitud: los que NO
     * están vencidos. Se filtra por `vigencia_fin` (indexada), que es el mismo
     * criterio del accessor — no una segunda regla.
     */
    public function scopeVigentes($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('vigencia_fin')
              ->orWhere('vigencia_fin', '>=', now()->startOfDay());
        });
    }

    public function scopeDeEmpresa($query, int $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }
}
