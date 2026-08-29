<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Lo que falta de un concepto: quién lo consigue y para cuándo.
 */
class DcPendiente extends Model
{
    use SoftDeletes;

    protected $table = 'dc_pendientes';

    protected $fillable = [
        'empresa_id', 'concepto_id', 'responsable_user_id', 'fecha_compromiso',
        'estado', 'comentarios', 'recordatorio_enviado_at',
    ];

    protected $casts = [
        'fecha_compromiso'        => 'date',
        'recordatorio_enviado_at' => 'datetime',
    ];

    protected $appends = ['vencido'];

    public const ESTADOS = ['pendiente', 'en_proceso', 'entregado', 'no_aplica'];

    /** Días de antelación con los que un pendiente entra en alcance de recordatorio. */
    public const DIAS_AVISO = 7;

    /**
     * ¿Está vencido? DERIVADO, no columna — mismo criterio que
     * `DcDocumento::getEstadoAttribute()`: una copia persistida envejecería sola
     * a medianoche sin que nadie escriba esa fila.
     */
    public function getVencidoAttribute(): bool
    {
        if ($this->fecha_compromiso === null || in_array($this->estado, ['entregado', 'no_aplica'], true)) {
            return false;
        }

        return $this->fecha_compromiso->lt(now()->startOfDay());
    }

    public function scopePorVencerOVencidos($query)
    {
        return $query->abiertos()
            ->whereNotNull('fecha_compromiso')
            ->where('fecha_compromiso', '<=', now()->startOfDay()->addDays(self::DIAS_AVISO));
    }

    public function concepto()
    {
        return $this->belongsTo(DcConcepto::class, 'concepto_id');
    }

    public function responsable()
    {
        return $this->belongsTo(User::class, 'responsable_user_id');
    }

    public function scopeAbiertos($query)
    {
        return $query->whereIn('estado', ['pendiente', 'en_proceso']);
    }

    public function scopeDeEmpresa($query, int $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }
}
